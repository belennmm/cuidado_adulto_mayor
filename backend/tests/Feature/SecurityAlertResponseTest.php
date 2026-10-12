<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use App\Services\SecurityEventRecorder;
use App\Support\AuditRecordIntegrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class SecurityAlertResponseTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/alert-response-'.Str::uuid());
        File::ensureDirectoryExists($this->directory);
        config([
            'logging.channels.security_audit.path' => $this->directory.'/audit.log',
            'security_audit.collector_url' => null,
            'security_audit.alerts.brute_force' => 2,
            'security_audit.alerts.denials' => 2,
            'security_audit.alerts.mass_access' => 2,
        ]);
        Log::forgetChannel('security_audit');
        $this->path = Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
    }

    protected function tearDown(): void
    {
        try {
            Log::channel('security_audit')->getLogger()->close();
            Log::forgetChannel('security_audit');
            File::delete($this->path);
            rmdir($this->directory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_failed_http_logins_produce_a_signed_alert_linked_to_the_triggering_request(): void
    {
        $payload = ['email' => 'alert-test@example.com', 'password' => 'private-wrong-password'];
        $first = $this->postJson('/api/login', $payload)->assertUnauthorized();
        $second = $this->postJson('/api/login', $payload)->assertUnauthorized();
        $records = $this->records();
        $this->assertCount(3, $records);
        $alert = $this->alert('brute_force');
        $this->assertSame('WARNING', $alert['level_name']);
        $this->assertSame($second->headers->get('X-Correlation-ID'), $alert['context']['correlation_id']);
        $this->assertNotSame($first->headers->get('X-Correlation-ID'), $alert['context']['correlation_id']);
        $this->assertNull($alert['context']['actor_id']);
        $content = File::get($this->path);
        $this->assertStringNotContainsString($payload['email'], $content);
        $this->assertStringNotContainsString($payload['password'], $content);
        $this->artisan('security:audit-verify', ['path' => $this->path])->assertExitCode(0);
    }

    public function test_http_denials_are_isolated_by_actor_even_on_the_same_ip(): void
    {
        $first = User::factory()->approvedFamily()->create();
        $second = User::factory()->approvedFamily()->create();
        Sanctum::actingAs($first, ['*']);
        $this->getJson('/api/admin/users')->assertForbidden();
        Sanctum::actingAs($second, ['*']);
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->assertCount(2, $this->records());
        Sanctum::actingAs($first, ['*']);
        $response = $this->getJson('/api/admin/users')->assertForbidden();
        $alert = $this->alert('denials');
        $this->assertSame($first->id, $alert['context']['actor_id']);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $alert['context']['correlation_id']);
        $this->assertCount(4, $this->records());
    }

    public function test_sensitive_http_reads_follow_the_actor_when_the_ip_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $adult = OlderAdult::factory()->create(['medical_history' => 'private-clinical-history']);
        Sanctum::actingAs($admin, ['*']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->getJson('/api/admin/older-adults/'.$adult->id)->assertOk();
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->getJson('/api/admin/older-adults/'.$adult->id)->assertOk();
        $alert = $this->alert('mass_access');
        $this->assertSame($admin->id, $alert['context']['actor_id']);
        $this->assertSame('198.51.100.0', $alert['context']['ip_address']);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $alert['context']['correlation_id']);
        $this->assertStringNotContainsString($adult->medical_history, File::get($this->path));
        $this->assertStringNotContainsString($adult->full_name, File::get($this->path));
    }

    public function test_public_http_success_does_not_create_an_alert(): void
    {
        $this->getJson('/api/ping')->assertOk();
        $this->getJson('/api/ping')->assertOk();
        $this->assertFileDoesNotExist($this->path);
    }

    public function test_cache_failure_preserves_the_base_event_without_exposing_the_exception(): void
    {
        RateLimiter::shouldReceive('hit')->once()->andThrow(new RuntimeException('private-cache-credentials'));
        $request = Request::create('/api/login', 'POST', [], [], [], ['REMOTE_ADDR' => '192.0.2.10']);
        $request->attributes->set('correlation_id', (string) Str::uuid());
        $operationalPath = $this->directory.'/operational.log';
        $previousErrorLog = ini_get('error_log');
        ini_set('error_log', $operationalPath);
        try {
            app(SecurityEventRecorder::class)->record($request, 'authentication.login', 'login', 'denied', null, 'route', 'api/login');
            $notice = File::get($operationalPath);
            $this->assertStringContainsString('security_alert_detection_unavailable', $notice);
            $this->assertStringNotContainsString('private-cache-credentials', $notice);
        } finally {
            ini_set('error_log', $previousErrorLog);
            File::delete($operationalPath);
        }
        $records = $this->records();
        $this->assertCount(1, $records);
        $this->assertSame('security.event', $records[0]['message']);
        $this->assertStringNotContainsString('private-cache-credentials', File::get($this->path));
    }

    public function test_alert_signature_detects_tampering_before_evidence_is_trusted(): void
    {
        config(['security_audit.alerts.brute_force' => 1]);
        $this->postJson('/api/login', ['email' => 'alert-test@example.com', 'password' => 'wrong'])
            ->assertUnauthorized();
        $alert = $this->alert('brute_force');
        $alert['context']['actor_id'] = 999;
        File::put($this->path, json_encode($alert, JSON_THROW_ON_ERROR)."\n");
        $this->artisan('security:audit-verify', ['path' => $this->path])->assertExitCode(1);
    }

    private function records(): array
    {
        $lines = array_filter(explode("\n", trim(File::get($this->path))));
        $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
        foreach ($records as $record) {
            $this->assertTrue(app(AuditRecordIntegrity::class)->verify($record));
        }

        return array_values($records);
    }

    private function alert(string $rule): array
    {
        $alerts = array_values(array_filter($this->records(),
            fn ($record) => $record['message'] === 'security.alert'
                && $record['context']['event'] === 'security.alert.'.$rule));
        $this->assertCount(1, $alerts);

        return $alerts[0];
    }
}

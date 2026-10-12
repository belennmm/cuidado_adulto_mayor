<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use App\Support\AuditRecordIntegrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityAuditLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/audit-lifecycle-'.Str::uuid());
        File::ensureDirectoryExists($this->directory);
        config([
            'security_audit.session_cache_store' => 'array',
            'security_audit.collector_url' => null,
            'security_audit.alerts.mass_access' => 2,
            'logging.channels.security_audit.path' => $this->directory.'/audit.log',
            'logging.default' => 'lifecycle_operational',
            'logging.channels.lifecycle_operational' => [
                'driver' => 'single', 'path' => $this->directory.'/operational.log', 'level' => 'debug',
            ],
        ]);
        Log::forgetChannel('security_audit');
        $this->path = Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('User-Agent', 'Private-Browser-A')
            ->withHeader('X-Correlation-ID', 'client-controlled-correlation');
    }

    protected function tearDown(): void
    {
        try {
            foreach (['security_audit', 'lifecycle_operational'] as $channel) {
                Log::channel($channel)->getLogger()->close();
                Log::forgetChannel($channel);
            }
            File::delete([$this->path, $this->directory.'/audit.log.budget.lock', $this->directory.'/operational.log']);
            rmdir($this->directory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_complete_authenticated_lifecycle_protects_traceability_privacy_and_signatures_without_new_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->approvedFamily()->create();
        $this->assertFalse(Schema::hasColumn('personal_access_tokens', 'session_ip_fingerprint'));
        $this->assertFalse(Schema::hasColumn('personal_access_tokens', 'session_agent_fingerprint'));
        Log::info('operational-marker-not-for-audit');
        $login = $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        $bearer = $login->json('token');
        $this->assertSame(['authentication.login'], $this->namesFor($login));
        $this->withToken($bearer);

        $data = [
            'full_name' => 'Persona privada integral', 'age' => 75, 'gender' => 'Masculino',
            'room' => 'A-101', 'status' => 'Estable', 'medical_history' => 'private-clinical-history',
        ];
        $created = $this->postJson('/api/admin/older-adults', $data)->assertCreated();
        $adultId = $created->json('older_adult.id');
        $this->assertSame(['sensitive_data.change'], $this->namesFor($created));
        $firstRead = $this->getJson('/api/admin/older-adults/'.$adultId)->assertOk();
        $this->assertSame(['sensitive_data.access'], $this->namesFor($firstRead));
        $secondRead = $this->withHeader('User-Agent', 'Private-Browser-B')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->getJson('/api/admin/older-adults/'.$adultId)->assertOk();
        $this->assertSame([
            'session.ip_changed', 'session.user_agent_changed', 'sensitive_data.access', 'security.alert.mass_access',
        ], $this->namesFor($secondRead));
        $alert = array_values(array_filter($this->records(), fn ($record) => $record['context']['event'] === 'security.alert.mass_access'))[0];
        $this->assertSame('CRITICAL', $alert['level_name']);
        $this->assertSame($admin->id, $alert['context']['actor_id']);
        $this->assertSame('198.51.100.0', $alert['context']['ip_address']);

        $data['medical_history'] = "private-updated-history\r\n{\"message\":\"forged-clinical-line\"}";
        $updated = $this->putJson('/api/admin/older-adults/'.$adultId, $data)->assertOk();
        $this->assertSame(['sensitive_data.change'], $this->namesFor($updated));
        $this->assertDatabaseHas('older_adults', ['id' => $adultId, 'medical_history' => $data['medical_history']]);
        $roleChanged = $this->putJson('/api/admin/users/'.$target->id, [
            'name' => $target->name, 'email' => $target->email, 'role' => 'profesional', 'is_approved' => false,
        ])->assertOk();
        $this->assertSame(['user.update', 'user.role_changed', 'account.status_changed'], $this->namesFor($roleChanged));
        $deleted = $this->deleteJson('/api/admin/older-adults/'.$adultId)->assertOk();
        $this->assertSame(['sensitive_data.change'], $this->namesFor($deleted));
        $this->assertDatabaseMissing('older_adults', ['id' => $adultId]);
        $logout = $this->postJson('/api/logout')->assertOk();
        $this->assertSame(['authentication.logout'], $this->namesFor($logout));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $denied = $this->getJson('/api/me')->assertUnauthorized();
        $this->assertSame(['authentication.required'], $this->namesFor($denied));

        $content = File::get($this->path);
        foreach ([$bearer, $admin->email, $target->email, $data['full_name'], 'private-clinical-history',
            'private-updated-history', 'forged-clinical-line', 'Private-Browser-A', 'Private-Browser-B',
            '192.0.2.10', '198.51.100.20', 'client-controlled-correlation', 'operational-marker-not-for-audit'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }
        $this->assertStringNotContainsString('security.event', File::get($this->directory.'/operational.log'));
        $this->artisan('security:audit-verify', ['path' => $this->path])->assertExitCode(0);
        $records = $this->records();
        $records[0]['context']['result'] = 'denied';
        File::put($this->path, implode("\n", array_map(fn ($record) => json_encode($record, JSON_THROW_ON_ERROR), $records))."\n");
        $this->artisan('security:audit-verify', ['path' => $this->path])->assertExitCode(1);
    }

    public function test_rejected_mutations_do_not_claim_success_or_change_the_database(): void
    {
        $admin = User::factory()->admin()->create();
        $adult = OlderAdult::factory()->create();
        $login = $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        $this->withToken($login->json('token'));
        $before = $adult->fresh()->getAttributes();
        $response = $this->putJson('/api/admin/older-adults/'.$adult->id, ['age' => -10])->assertUnprocessable();
        $this->assertSame([], $this->namesFor($response));
        $this->assertSame($before, $adult->fresh()->getAttributes());
        $this->artisan('security:audit-verify')->assertExitCode(0);
    }

    public function test_saturation_keeps_http_reads_working_and_preserves_the_critical_alert(): void
    {
        config(['security_audit.limits.events_per_minute' => 2]);
        Log::forgetChannel('security_audit');
        $admin = User::factory()->admin()->create();
        $adult = OlderAdult::factory()->create();
        $login = $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        $this->withToken($login->json('token'))->getJson('/api/admin/older-adults/'.$adult->id)->assertOk();
        $previousErrorLog = ini_get('error_log');
        ini_set('error_log', $this->directory.'/operational.log');
        try {
            $response = $this->getJson('/api/admin/older-adults/'.$adult->id)->assertOk();
            $this->getJson('/api/admin/older-adults/'.$adult->id)->assertOk();
        } finally {
            ini_set('error_log', $previousErrorLog);
        }
        $records = $this->records();
        $this->assertCount(3, $records);
        $this->assertSame(['INFO', 'INFO', 'CRITICAL'], array_column($records, 'level_name'));
        $this->assertSame(['security.alert.mass_access'], $this->namesFor($response));
        $this->assertStringContainsString('security_audit_capacity_limited', File::get($this->directory.'/operational.log'));
        $this->artisan('security:audit-verify')->assertExitCode(0);
    }

    private function namesFor(TestResponse $response): array
    {
        $correlation = $response->headers->get('X-Correlation-ID');
        $this->assertTrue(Str::isUuid($correlation));
        $records = array_filter($this->records(), fn ($record) => $record['context']['correlation_id'] === $correlation);

        return array_values(array_map(fn ($record) => $record['context']['event'], $records));
    }

    private function records(): array
    {
        $content = File::get($this->path);
        $lines = explode("\n", rtrim($content, "\n"));
        $this->assertSame(count($lines), substr_count($content, "\n"));
        $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
        foreach ($records as $record) {
            $this->assertTrue(app(AuditRecordIntegrity::class)->verify($record));
            $this->assertSame('security_audit', $record['channel']);
            $this->assertCount(10, $record['context']);
            $this->assertSame(1, $record['context']['schema_version']);
            $this->assertTrue(Str::isUuid($record['context']['correlation_id']));
            $this->assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/', $record['context']['occurred_at']);
        }

        return $records;
    }
}

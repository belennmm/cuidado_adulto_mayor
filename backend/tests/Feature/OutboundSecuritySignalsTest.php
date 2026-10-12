<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use App\Services\SecurityInfrastructureAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutboundSecuritySignalsTest extends TestCase
{
    use RefreshDatabase;

    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('channel')->with('security_audit')->andReturnSelf();
        Log::shouldReceive('log')->andReturnUsing(function ($level, $message, $context) {
            $this->events[] = ['level' => $level, 'message' => $message, ...$context];
        });
    }

    private function names(): array
    {
        return array_column($this->events, 'event');
    }

    public function test_missing_resource_is_audited_once_without_including_user_input(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
        $response = $this->getJson('/api/admin/older-adults/999999')->assertNotFound();
        $this->assertSame(['resource.not_found'], $this->names());
        $this->assertSame($response->headers->get('X-Correlation-ID'), $this->events[0]['correlation_id']);
        $this->assertSame('denied', $this->events[0]['result']);
        $this->assertStringNotContainsString('999999', json_encode($this->events));
    }

    public function test_another_users_resource_is_denied_and_audited_without_returning_clinical_data(): void
    {
        $family = User::factory()->approvedFamily()->create();
        $adult = OlderAdult::factory()->create(['medical_history' => 'private-history']);
        Sanctum::actingAs($family, ['*']);
        $response = $this->getJson('/api/family/older-adults/'.$adult->id);
        $this->assertContains($response->status(), [403, 404]);
        $this->assertContains($this->events[0]['event'], ['authorization.denied', 'resource.not_found']);
        $this->assertStringNotContainsString('private-history', $response->getContent().json_encode($this->events));
    }

    public function test_repeated_missing_resources_generate_a_denial_alert(): void
    {
        config(['security_audit.alerts.denials' => 2]);
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
        $this->getJson('/api/admin/older-adults/999999')->assertNotFound();
        $this->getJson('/api/admin/older-adults/999998')->assertNotFound();
        $this->assertSame(['resource.not_found', 'resource.not_found', 'security.alert.denials'], $this->names());
    }

    public function test_failed_logins_follow_the_account_across_different_ips(): void
    {
        config(['security_audit.alerts.brute_force' => 2]);
        foreach (['192.0.2.1', '198.51.100.1'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/login', [
                'email' => 'private-account@example.com', 'password' => 'private-secret',
            ])->assertUnauthorized();
        }
        $this->assertSame(['authentication.login', 'authentication.login', 'security.alert.brute_force'], $this->names());
        $this->assertStringNotContainsString('private-account', json_encode($this->events));
        $this->assertStringNotContainsString('private-secret', json_encode($this->events));
    }

    public function test_role_and_approval_changes_generate_one_correlated_alert(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
        $target = User::factory()->approvedFamily()->create();
        $response = $this->putJson('/api/admin/users/'.$target->id, [
            'name' => $target->name, 'email' => $target->email, 'role' => 'profesional', 'is_approved' => false,
        ])->assertOk();
        $this->assertSame(['user.update', 'user.role_changed', 'security.alert.account_change', 'account.status_changed'], $this->names());
        $this->assertSame($response->headers->get('X-Correlation-ID'), $this->events[2]['correlation_id']);
    }

    public function test_administrative_account_lifecycle_is_explicitly_recorded(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
        $target = $this->postJson('/api/admin/users', [
            'name' => 'Private Admin', 'email' => 'private-admin@example.com', 'role' => 'admin', 'password' => 'Secure-Test!123',
        ])->assertCreated()->json('user.id');
        $this->assertSame(['user.create', 'administrative_account.create'], $this->names());
        $this->events = [];
        $this->putJson('/api/admin/users/'.$target, [
            'name' => 'Updated Admin', 'email' => 'private-admin@example.com', 'role' => 'admin', 'is_approved' => true,
        ])->assertOk();
        $this->assertSame(['user.update', 'administrative_account.update'], $this->names());
        $this->events = [];
        $this->deleteJson('/api/admin/users/'.$target)->assertOk();
        $this->assertSame(['user.delete', 'administrative_account.delete'], $this->names());
        $this->assertStringNotContainsString('private-admin', json_encode($this->events));
    }

    public function test_large_sensitive_response_generates_a_critical_alert_without_copying_the_body(): void
    {
        config(['security_audit.large_response_rows' => 2]);
        OlderAdult::factory()->count(2)->create(['medical_history' => 'private-history']);
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
        $this->getJson('/api/admin/older-adults')->assertOk();
        $this->assertSame(['sensitive_data.access', 'sensitive_data.large_response', 'security.alert.large_response'], $this->names());
        $this->assertSame('critical', $this->events[2]['level']);
        $this->assertStringNotContainsString('private-history', json_encode($this->events));
    }

    public function test_infrastructure_failures_preserve_utc_correlation_and_never_exception_text(): void
    {
        request()->attributes->set('correlation_id', '11111111-1111-4111-8111-111111111111');
        foreach (['database', 'authentication', 'storage'] as $service) {
            app(SecurityInfrastructureAudit::class)->record($service);
            app(SecurityInfrastructureAudit::class)->record($service);
        }
        $this->assertSame(['infrastructure.database', 'infrastructure.authentication', 'infrastructure.storage'], $this->names());
        foreach ($this->events as $event) {
            $this->assertSame('error', $event['level']);
            $this->assertStringEndsWith('Z', $event['occurred_at']);
            $this->assertSame('11111111-1111-4111-8111-111111111111', $event['correlation_id']);
        }
    }

    public function test_database_exception_is_classified_without_logging_sql_or_credentials(): void
    {
        Route::get('api/outbound-test-database-failure', function () {
            throw new \Illuminate\Database\QueryException('sqlite', 'SELECT private-clinical-data', ['private-password'], new \PDOException('private-db-host'));
        });
        Log::shouldReceive('error')->once()->with('API request failed.', ['exception_type' => \Illuminate\Database\QueryException::class]);

        $response = $this->getJson('/api/outbound-test-database-failure')->assertStatus(500);

        $this->assertContains('infrastructure.database', $this->names());
        $encoded = $response->getContent().json_encode($this->events);
        foreach (['private-clinical-data', 'private-password', 'private-db-host'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    public function test_storage_exception_is_classified_without_logging_private_paths(): void
    {
        Route::get('api/outbound-test-storage-failure', function () {
            throw new \Illuminate\Contracts\Filesystem\FileNotFoundException('private-storage-path');
        });
        Log::shouldReceive('error')->once()->with('API request failed.', ['exception_type' => \Illuminate\Contracts\Filesystem\FileNotFoundException::class]);

        $response = $this->getJson('/api/outbound-test-storage-failure')->assertStatus(500);

        $this->assertContains('infrastructure.storage', $this->names());
        $this->assertStringNotContainsString('private-storage-path', $response->getContent().json_encode($this->events));
    }
}

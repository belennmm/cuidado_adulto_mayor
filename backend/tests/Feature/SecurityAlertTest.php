<?php

namespace Tests\Feature;

use App\Services\SecurityAlertService;
use App\Services\SecurityEventRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityAlertTest extends TestCase
{
    #[DataProvider('suspiciousEvents')]
    public function test_threshold_emits_one_correlated_alert_without_secrets(string $event, string $action, string $result, string $rule): void
    {
        config(['security_audit.alerts.'.$rule => 3]);
        $alerts = [];
        Log::shouldReceive('channel')->with('security_audit')->andReturnSelf();
        Log::shouldReceive('info')->times(4);
        Log::shouldReceive('warning')->once()->andReturnUsing(function ($message, $context) use (&$alerts) {
            $this->assertSame('security.alert', $message);
            $alerts[] = $context;
        });
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $request = $this->request('192.0.2.10');
            app(SecurityEventRecorder::class)->record($request, $event, $action, $result, 42, 'route', 'api/admin/users');
            $this->assertCount($attempt < 3 ? 0 : 1, $alerts);
        }
        $this->assertSame('security.alert.'.$rule, $alerts[0]['event']);
        $this->assertSame('triggered', $alerts[0]['result']);
        $this->assertSame('192.0.2.0', $alerts[0]['ip_address']);
        $this->assertSame(42, $alerts[0]['actor_id']);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $alerts[0]['correlation_id']);
        $this->assertStringNotContainsString('private-password', json_encode($alerts));
    }

    public static function suspiciousEvents(): array
    {
        return [
            ['authentication.login', 'login', 'denied', 'brute_force'],
            ['authentication.login', 'login', 'blocked', 'brute_force'],
            ['authorization.denied', 'access', 'denied', 'denials'],
            ['authentication.required', 'access', 'denied', 'denials'],
            ['sensitive_data.access', 'read', 'success', 'mass_access'],
        ];
    }

    public function test_counter_expires_and_cooldown_allows_later_alerts(): void
    {
        config(['security_audit.alerts.brute_force' => 2]);
        $alerts = 0;
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->times(7);
        Log::shouldReceive('warning')->twice()->andReturnUsing(function () use (&$alerts) { $alerts++; });
        $record = fn () => app(SecurityEventRecorder::class)->record($this->request('192.0.2.10'), 'authentication.login', 'login', 'denied', null, 'route', 'api/login');
        try {
            $record();
            $this->travel(61)->seconds();
            $record();
            $this->assertSame(0, $alerts);
            $record();
            $this->assertSame(1, $alerts);
            $this->travel(61)->seconds();
            $record();
            $record();
            $this->assertSame(1, $alerts);
            $this->travel(301)->seconds();
            $record();
            $record();
            $this->assertSame(2, $alerts);
        } finally {
            $this->travelBack();
        }
    }

    public function test_sources_are_isolated_and_duplicate_events_in_one_request_count_once(): void
    {
        config(['security_audit.alerts.brute_force' => 2]);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->times(4);
        Log::shouldReceive('warning')->once();
        $request = $this->request('192.0.2.10');
        $recorder = app(SecurityEventRecorder::class);
        $recorder->record($request, 'authentication.login', 'login', 'denied', null, 'route', 'api/login');
        $recorder->record($request, 'authentication.login', 'login', 'denied', null, 'route', 'api/login');
        $recorder->record($this->request('192.0.2.11'), 'authentication.login', 'login', 'denied', null, 'route', 'api/login');
        $recorder->record($this->request('192.0.2.10'), 'authentication.login', 'login', 'denied', null, 'route', 'api/login');
    }

    public function test_successful_login_does_not_trigger_brute_force_alerts(): void
    {
        config(['security_audit.alerts.brute_force' => 1]);
        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();
        app(SecurityEventRecorder::class)->record($this->request('192.0.2.10'), 'authentication.login', 'login', 'success', 42, 'route', 'api/login');
    }

    public function test_invalid_thresholds_are_rejected(): void
    {
        config(['security_audit.alerts.denials' => 0]);
        $this->expectException(InvalidArgumentException::class);
        app(SecurityAlertService::class)->settings();
    }

    private function request(string $ip): Request
    {
        $request = Request::create('/api/example', 'POST', ['password' => 'private-password'], [], [], ['REMOTE_ADDR' => $ip]);
        $request->attributes->set('correlation_id', '11111111-1111-4111-8111-111111111111');

        return $request;
    }
}

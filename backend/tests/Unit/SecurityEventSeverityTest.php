<?php

namespace Tests\Unit;

use App\Support\SecurityEventSeverity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityEventSeverityTest extends TestCase
{
    #[DataProvider('events')]
    public function test_events_have_consistent_severity(string $event, string $result, string $level): void
    {
        $this->assertSame($level, SecurityEventSeverity::level($event, $result));
    }

    public static function events(): array
    {
        return [
            ['authentication.login', 'success', 'info'],
            ['sensitive_data.change', 'success', 'info'],
            ['user.role_changed', 'success', 'info'],
            ['authentication.login', 'denied', 'warning'],
            ['authentication.login', 'blocked', 'warning'],
            ['authorization.denied', 'denied', 'warning'],
            ['security.alert.brute_force', 'triggered', 'warning'],
            ['security.alert.denials', 'triggered', 'warning'],
            ['security.alert.detection_unavailable', 'failure', 'error'],
            ['security.example', 'error', 'error'],
            ['security.alert.mass_access', 'triggered', 'critical'],
            ['security.alert.mass_access', 'failure', 'error'],
            ['security.example', 'success', 'info'],
        ];
    }
}

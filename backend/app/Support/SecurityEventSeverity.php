<?php

namespace App\Support;

final class SecurityEventSeverity
{
    public static function level(string $event, string $result): string
    {
        return match (true) {
            $event === 'security.alert.mass_access' && $result === 'triggered' => 'critical',
            in_array($result, ['error', 'failure'], true) => 'error',
            in_array($result, ['denied', 'blocked', 'triggered'], true) => 'warning',
            default => 'info',
        };
    }
}

<?php

namespace App\Support;

use InvalidArgumentException;

final class AuditLogLimits
{
    public static function settings(): array
    {
        $ranges = [
            'events_per_minute' => [1, 100000],
            'urgent_per_minute' => [1, 100000],
            'record_bytes' => [1024, 65536],
            'daily_bytes' => [4096, 1073741824],
        ];
        $settings = [];
        foreach ($ranges as $name => [$minimum, $maximum]) {
            $value = filter_var(config('security_audit.limits.'.$name), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => $minimum, 'max_range' => $maximum],
            ]);
            if ($value === false) {
                throw new InvalidArgumentException('Limite de auditoria invalido: '.$name);
            }
            $settings[$name] = $value;
        }
        if ($settings['daily_bytes'] < $settings['record_bytes']) {
            throw new InvalidArgumentException('El limite diario debe admitir al menos un registro.');
        }

        return $settings;
    }
}

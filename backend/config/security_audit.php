<?php

return [
    'integrity_key' => env('SECURITY_AUDIT_INTEGRITY_KEY'),
    'key_id' => env('SECURITY_AUDIT_KEY_ID', 'v1'),
    'collector_url' => env('SECURITY_AUDIT_COLLECTOR_URL'),
    'retention_days' => env('SECURITY_AUDIT_RETENTION_DAYS', 90),
    'alerts' => [
        'window_seconds' => env('SECURITY_ALERT_WINDOW_SECONDS', 60),
        'cooldown_seconds' => env('SECURITY_ALERT_COOLDOWN_SECONDS', 300),
        'brute_force' => env('SECURITY_ALERT_BRUTE_FORCE', 10),
        'denials' => env('SECURITY_ALERT_DENIALS', 10),
        'mass_access' => env('SECURITY_ALERT_MASS_ACCESS', 100),
    ],
];

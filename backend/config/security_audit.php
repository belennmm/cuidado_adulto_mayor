<?php

return [
    'integrity_key' => env('SECURITY_AUDIT_INTEGRITY_KEY'),
    'key_id' => env('SECURITY_AUDIT_KEY_ID', 'v1'),
    'collector_url' => env('SECURITY_AUDIT_COLLECTOR_URL'),
    'retention_days' => env('SECURITY_AUDIT_RETENTION_DAYS', 90),
    'session_cache_store' => env('SECURITY_SESSION_CACHE_STORE', 'file'),
    'large_response_bytes' => env('SECURITY_AUDIT_LARGE_RESPONSE_BYTES', 1048576),
    'large_response_rows' => env('SECURITY_AUDIT_LARGE_RESPONSE_ROWS', 100),
    'limits' => [
        'events_per_minute' => env('SECURITY_AUDIT_EVENTS_PER_MINUTE', 500),
        'urgent_per_minute' => env('SECURITY_AUDIT_URGENT_PER_MINUTE', 100),
        'record_bytes' => env('SECURITY_AUDIT_RECORD_BYTES', 8192),
        'daily_bytes' => env('SECURITY_AUDIT_DAILY_BYTES', 52428800),
    ],
    'alerts' => [
        'window_seconds' => env('SECURITY_ALERT_WINDOW_SECONDS', 60),
        'cooldown_seconds' => env('SECURITY_ALERT_COOLDOWN_SECONDS', 300),
        'brute_force' => env('SECURITY_ALERT_BRUTE_FORCE', 10),
        'denials' => env('SECURITY_ALERT_DENIALS', 10),
        'mass_access' => env('SECURITY_ALERT_MASS_ACCESS', 100),
    ],
];

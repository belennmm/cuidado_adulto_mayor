<?php

return [
    'integrity_key' => env('SECURITY_AUDIT_INTEGRITY_KEY'),
    'key_id' => env('SECURITY_AUDIT_KEY_ID', 'v1'),
    'collector_url' => env('SECURITY_AUDIT_COLLECTOR_URL'),
    'retention_days' => env('SECURITY_AUDIT_RETENTION_DAYS', 90),
];

<?php

return [
    'https_hosts' => ['api.pwnedpasswords.com' => [443]],
    'tls_hosts' => env('OUTBOUND_AUDIT_COLLECTOR_HOST') ? [
        strtolower((string) env('OUTBOUND_AUDIT_COLLECTOR_HOST')) => [(int) env('OUTBOUND_AUDIT_COLLECTOR_PORT', 6514)],
    ] : [],
];

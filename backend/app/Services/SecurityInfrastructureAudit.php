<?php

namespace App\Services;

use App\Support\SecurityEvent;
use App\Support\SecurityEventPrivacy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SecurityInfrastructureAudit
{
    public function record(string $service, string $result = 'failure'): void
    {
        if (! in_array($service, ['database', 'authentication', 'storage', 'outbound_request', 'application'], true)) {
            $service = 'application';
        }
        try {
            $request = request();
            $flag = 'infrastructure_audited_'.$service;
            if ($request->attributes->get($flag)) {
                return;
            }
            $request->attributes->set($flag, true);
            $context = SecurityEventPrivacy::filter((new SecurityEvent(
                'infrastructure.'.$service, 'operate', $result,
                resourceType: 'service', ipAddress: $request->ip(),
                correlationId: $request->attributes->get('correlation_id') ?? (string) Str::uuid(),
            ))->toArray());
            Log::channel('security_audit')->log('error', 'security.event', $context);
        } catch (Throwable) {
            error_log('security_infrastructure_audit_unavailable');
        }
    }
}

<?php

namespace App\Services;

use App\Support\SecurityEvent;
use App\Support\SecurityEventPrivacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityEventRecorder
{
    public function record(
        Request $request,
        string $event,
        string $action,
        string $result,
        ?int $actorId,
        ?string $resourceType,
        int|string|null $resourceId,
    ): void {
        $securityEvent = new SecurityEvent(
            event: $event,
            action: $action,
            result: $result,
            actorId: $actorId,
            resourceType: $resourceType,
            resourceId: $resourceId,
            ipAddress: $request->ip(),
            correlationId: $request->attributes->get('correlation_id'),
        );

        $context = SecurityEventPrivacy::filter($securityEvent->toArray());
        Log::channel('security_audit')->info('security.event', $context);
        app(SecurityAlertService::class)->observe($request, $context);
    }
}

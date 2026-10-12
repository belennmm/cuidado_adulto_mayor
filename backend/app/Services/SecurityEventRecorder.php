<?php

namespace App\Services;

use App\Support\SecurityEvent;
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

        Log::info('security.event', $securityEvent->toArray());
    }
}

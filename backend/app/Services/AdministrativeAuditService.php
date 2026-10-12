<?php

namespace App\Services;

use App\Support\ResourceAccess;
use App\Support\SecurityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdministrativeAuditService
{
    public function recordResponse(Request $request, Response $response): void
    {
        if (! $request->is('api/*') || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300 || $request->attributes->get('administrative_audited')) {
            return;
        }

        $route = $request->route();
        $actor = $request->user();
        if (! $route || ! $actor) {
            return;
        }

        $admin = $request->is('api/admin/*') && ResourceAccess::admin($actor);
        $export = $admin && preg_match('/^attachment(?:;|$)/i', $response->headers->get('Content-Disposition', '')) === 1;
        $deletion = $request->isMethod('DELETE');
        if (! $admin && ! $deletion) {
            return;
        }

        if (! $export && ($request->attributes->get('sensitive_data_audited')
            || $request->attributes->get('account_medication_audited'))) {
            return;
        }

        $action = $export ? 'export' : match ($route->getActionMethod()) {
            'approveChangeRequest' => 'approve_change',
            'rejectChangeRequest' => 'reject_change',
            'approve' => 'approve',
            'reject' => 'reject',
            default => match ($request->method()) {
                'GET', 'HEAD' => 'read',
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => null,
            },
        };
        if ($action === null) {
            return;
        }

        $type = 'route';
        $id = $route->uri();
        foreach (['schedule' => 'caregiver_schedule', 'vacationRequest' => 'vacation_request'] as $parameter => $resourceType) {
            $resource = $route->parameter($parameter);
            if ($resource instanceof Model) {
                $type = $resourceType;
                $id = $resource->getKey();
                break;
            }
        }
        if ($response->getStatusCode() === 201 && $response instanceof JsonResponse) {
            foreach (['schedule' => 'caregiver_schedule', 'vacation_request' => 'vacation_request'] as $key => $resourceType) {
                $createdId = data_get($response->getData(true), $key.'.id');
                if (is_int($createdId)) {
                    $type = $resourceType;
                    $id = $createdId;
                    break;
                }
            }
        }

        $event = new SecurityEvent(
            event: $export ? 'administrative.export' : ($deletion ? 'resource.deleted' : 'administrative.action'),
            action: $action,
            result: 'success',
            actorId: (int) $actor->getAuthIdentifier(),
            resourceType: $type,
            resourceId: $id,
            ipAddress: $request->ip(),
            correlationId: $request->attributes->get('correlation_id'),
        );
        Log::info('security.event', $event->toArray());
        $request->attributes->set('administrative_audited', true);
    }
}

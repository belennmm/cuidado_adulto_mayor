<?php

namespace App\Services;

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MedicationInventoryController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountMedicationAuditService
{
    public function __construct(private readonly SecurityEventRecorder $recorder) {}

    public function recordResponse(Request $request, Response $response): void
    {
        if (! $request->is('api/*') || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300 || $request->attributes->get('account_medication_audited')) {
            return;
        }

        $route = $request->route();
        if (! $route) {
            return;
        }

        [$controller, $method] = array_pad(explode('@', $route->getActionName(), 2), 2, null);
        $action = match ($method) {
            'store', 'register' => 'create',
            'update', 'updateMe' => 'update',
            'approve' => 'approve',
            'reject' => 'reject',
            'destroy' => 'delete',
            'adjustStock' => 'adjust_stock',
            default => null,
        };
        $resource = match ($controller) {
            AdminUserController::class => ['user', 'user', 'user'],
            MedicationInventoryController::class => ['medication_inventory', 'inventoryItem', 'medication'],
            AuthController::class => in_array($method, ['register', 'updateMe'], true) ? ['account', null, 'user'] : null,
            default => null,
        };

        if ($action === null || $resource === null) {
            return;
        }

        [$type, $parameter, $responseKey] = $resource;
        if ($controller === AuthController::class && $method === 'register'
            && ! $request->attributes->has('registered_account_id')) {
            return;
        }
        $resourceId = $parameter === null ? null : $route->parameter($parameter);
        if ($controller === AuthController::class && $method === 'register') {
            $resourceId = $request->attributes->get('registered_account_id');
        }
        if ($resourceId instanceof Model) {
            $resourceId = $resourceId->getKey();
        }
        if ($resourceId === null && $response instanceof JsonResponse) {
            $resourceId = data_get($response->getData(true), $responseKey.'.id');
        }
        if (! is_int($resourceId) && ! (is_string($resourceId) && ctype_digit($resourceId))) {
            $resourceId = null;
        }

        $events = [$type.'.'.$action];
        if ($controller === AdminUserController::class) {
            foreach (['role' => 'user.role_changed', 'is_approved' => 'account.status_changed', 'password' => 'account.credentials_changed'] as $field => $event) {
                if (in_array($field, $request->attributes->get('changed_account_fields', []), true)) {
                    $events[] = $event;
                }
            }
        }

        foreach ($events as $name) {
            $this->recorder->record(
                request: $request,
                event: $name,
                action: $action,
                result: 'success',
                actorId: $method === 'register' ? null : $request->user()?->getAuthIdentifier(),
                resourceType: $type,
                resourceId: $resourceId,
            );
        }

        $request->attributes->set('account_medication_audited', true);
    }
}

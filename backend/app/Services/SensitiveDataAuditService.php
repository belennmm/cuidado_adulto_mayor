<?php

namespace App\Services;

use App\Http\Controllers\FamilyCareController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MedicationAdministrationController;
use App\Http\Controllers\MobilityExerciseController;
use App\Http\Controllers\OlderAdultController;
use App\Http\Controllers\ProfessionalCareController;
use App\Http\Controllers\ProfessionalIncidentController;
use App\Http\Controllers\ProfessionalRoutineNoteController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RutinaController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SensitiveDataAuditService
{
    public function __construct(private readonly SecurityEventRecorder $recorder) {}

    private const RESOURCES = [
        OlderAdultController::class => ['older_adult', 'olderAdult', 'older_adult'],
        ProfessionalRoutineNoteController::class => ['routine_note', 'routineNote', 'note'],
        RutinaController::class => ['routine', 'rutina', 'rutina'],
        IncidentController::class => ['incident', 'incident', 'incident'],
        ProfessionalIncidentController::class => ['incident', 'incident', 'incident'],
        MobilityExerciseController::class => ['mobility_exercise', 'mobilityExercise', 'exercise'],
        MedicationAdministrationController::class => ['medication_assignment', 'assignment', null],
        ReminderController::class => ['medication_reminder', null, null],
    ];

    public function recordResponse(Request $request, Response $response): void
    {
        if (! $request->is('api/*') || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300 || $request->attributes->get('sensitive_data_audited')) {
            return;
        }

        $route = $request->route();
        if (! $route || ! $request->user()) {
            return;
        }

        [$controller, $method] = array_pad(explode('@', $route->getActionName(), 2), 2, null);
        $resource = self::RESOURCES[$controller] ?? null;

        if (in_array($controller, [FamilyCareController::class, ProfessionalCareController::class], true)) {
            $resource = match ($method) {
                'overview', 'olderAdults', 'olderAdult' => ['older_adult', 'olderAdult', null],
                'routine' => ['routine', null, null],
                'incidents' => ['incident', null, null],
                'olderAdultIncidents' => ['older_adult_incidents', 'olderAdult', null],
                default => null,
            };
        }

        $action = match ($request->method()) {
            'GET', 'HEAD' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => null,
        };

        if ($resource === null || $action === null) {
            return;
        }

        [$type, $parameter, $responseKey] = $resource;
        $resourceId = $parameter === null ? null : $route->parameter($parameter);
        if ($resourceId instanceof Model) {
            $resourceId = $resourceId->getKey();
        }
        if ($resourceId === null && $response->getStatusCode() === 201
            && $responseKey !== null && $response instanceof JsonResponse) {
            $resourceId = data_get($response->getData(true), $responseKey.'.id');
        }
        if (! is_int($resourceId) && ! (is_string($resourceId) && ctype_digit($resourceId))) {
            $resourceId = null;
        }

        $this->recorder->record(
            request: $request,
            event: $action === 'read' ? 'sensitive_data.access' : 'sensitive_data.change',
            action: $action,
            result: 'success',
            actorId: (int) $request->user()->getAuthIdentifier(),
            resourceType: $type,
            resourceId: $resourceId,
        );

        $request->attributes->set('sensitive_data_audited', true);
    }
}

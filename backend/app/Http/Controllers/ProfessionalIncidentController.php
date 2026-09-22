<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfessionalIncidentRequest;
use App\Http\Resources\ProfessionalIncidentResource;
use App\Models\Incident;
use App\Services\ProfessionalIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalIncidentController extends Controller
{
    public function __construct(private readonly ProfessionalIncidentService $incidentService) {}

    public function store(ProfessionalIncidentRequest $request): JsonResponse
    {
        $incident = $this->incidentService->create($request->user(), $request->validated());

        return $this->incidentResponse(
            'Incidente registrado correctamente.', $incident, $request, 201,
        );
    }

    public function update(
        ProfessionalIncidentRequest $request,
        Incident $incident,
    ): JsonResponse {
        $incident = $this->incidentService->update(
            $request->user(), $incident, $request->validated(),
        );

        return $this->incidentResponse(
            'Incidente actualizado correctamente.', $incident, $request,
        );
    }

    private function incidentResponse(
        string $message,
        Incident $incident,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'incident' => ProfessionalIncidentResource::make($incident)->toArray($request),
        ], $status);
    }
}

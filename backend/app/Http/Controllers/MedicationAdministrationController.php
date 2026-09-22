<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicationAdministrationRequest;
use App\Http\Resources\MedicationAdministrationResource;
use App\Models\OlderAdultMedication;
use App\Services\MedicationAdministrationService;
use Illuminate\Http\JsonResponse;

class MedicationAdministrationController extends Controller
{
    public function __construct(
        private readonly MedicationAdministrationService $administrationService,
    ) {}

    public function markTaken(
        MedicationAdministrationRequest $request,
        OlderAdultMedication $assignment,
    ): JsonResponse {
        $administration = $this->administrationService->markTaken(
            $request->user(),
            $assignment,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Medicamento marcado como tomado.',
            'administration' => MedicationAdministrationResource::make($administration)
                ->toArray($request),
        ]);
    }
}

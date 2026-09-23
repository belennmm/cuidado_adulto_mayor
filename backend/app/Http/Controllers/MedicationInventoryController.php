<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicationInventoryRequest;
use App\Http\Resources\MedicationInventoryResource;
use App\Models\OlderAdultMedication;
use App\Services\MedicationInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationInventoryController extends Controller
{
    public function __construct(private readonly MedicationInventoryService $inventoryService) {}

    public function index(MedicationInventoryRequest $request): JsonResponse
    {
        $items = $this->inventoryService->inventory($request->validated('older_adult_id'));

        return response()->json([
            'inventory' => $items
                ->map(fn (OlderAdultMedication $item) => $this->resource($item, $request))
                ->values(),
        ]);
    }

    public function store(MedicationInventoryRequest $request): JsonResponse
    {
        $item = $this->inventoryService->create($request->validated());

        return $this->medicationResponse(
            'Medicamento agregado al inventario del adulto mayor.', $item, $request, 201,
        );
    }

    public function update(
        MedicationInventoryRequest $request,
        OlderAdultMedication $inventoryItem,
    ): JsonResponse {
        $item = $this->inventoryService->update($inventoryItem, $request->validated());

        return $this->medicationResponse(
            'Inventario del adulto mayor actualizado correctamente.', $item, $request,
        );
    }

    public function adjustStock(
        MedicationInventoryRequest $request,
        OlderAdultMedication $inventoryItem,
    ): JsonResponse {
        $data = $request->validated();
        $item = $this->inventoryService->adjustStock(
            $inventoryItem,
            $data['action'],
            (int) $data['amount'],
        );
        $message = $data['action'] === 'increase'
            ? 'Stock aumentado correctamente.'
            : 'Stock reducido correctamente.';

        return $this->medicationResponse($message, $item, $request);
    }

    public function destroy(OlderAdultMedication $inventoryItem): JsonResponse
    {
        $this->inventoryService->delete($inventoryItem);

        return response()->json([
            'message' => 'Medicamento eliminado del inventario del adulto mayor.',
        ]);
    }

    private function medicationResponse(
        string $message,
        OlderAdultMedication $item,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'medication' => $this->resource($item, $request),
        ], $status);
    }

    private function resource(OlderAdultMedication $item, Request $request): array
    {
        return MedicationInventoryResource::make($item)->toArray($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicationInventoryRequest;
use App\Models\Medication;
use App\Models\OlderAdultMedication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MedicationInventoryController extends Controller
{
    public function index(MedicationInventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'inventory' => $this->inventoryItems($data['older_adult_id'] ?? null),
        ]);
    }

    public function store(MedicationInventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $inventoryItem = DB::transaction(function () use ($data) {
            $medication = Medication::firstOrCreate(
                ['name' => trim($data['name'])],
                ['is_active' => true],
            );

            return OlderAdultMedication::create([
                ...$this->assignmentData($data),
                'medication_id' => $medication->id,
            ]);
        });

        return response()->json([
            'message' => 'Medicamento agregado al inventario del adulto mayor.',
            'medication' => $this->formatInventoryItem($inventoryItem->load(['medication', 'olderAdult'])),
        ], 201);
    }

    public function update(MedicationInventoryRequest $request, OlderAdultMedication $inventoryItem): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($inventoryItem, $data) {
            $medication = Medication::firstOrCreate(
                ['name' => trim($data['name'])],
                ['is_active' => true],
            );

            $inventoryItem->update([
                ...$this->assignmentData($data),
                'medication_id' => $medication->id,
            ]);
        });

        return response()->json([
            'message' => 'Inventario del adulto mayor actualizado correctamente.',
            'medication' => $this->formatInventoryItem($inventoryItem->refresh()->load(['medication', 'olderAdult'])),
        ]);
    }

    public function adjustStock(MedicationInventoryRequest $request, OlderAdultMedication $inventoryItem): JsonResponse
    {
        $data = $request->validated();

        $amount = (int) $data['amount'];
        $currentQuantity = (int) $inventoryItem->quantity;
        $nextQuantity = $data['action'] === 'increase'
            ? $currentQuantity + $amount
            : $currentQuantity - $amount;

        if ($nextQuantity < 0) {
            return response()->json([
                'message' => 'La cantidad no puede quedar negativa.',
            ], 422);
        }

        $inventoryItem->update(['quantity' => $nextQuantity]);

        return response()->json([
            'message' => $data['action'] === 'increase'
                ? 'Stock aumentado correctamente.'
                : 'Stock reducido correctamente.',
            'medication' => $this->formatInventoryItem($inventoryItem->refresh()->load(['medication', 'olderAdult'])),
        ]);
    }

    public function destroy(OlderAdultMedication $inventoryItem): JsonResponse
    {
        if ($inventoryItem->administrations()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar este inventario porque tiene administraciones registradas.',
            ], 422);
        }

        $inventoryItem->delete();

        return response()->json([
            'message' => 'Medicamento eliminado del inventario del adulto mayor.',
        ]);
    }

    private function assignmentData(array $data): array
    {
        return [
            'older_adult_id' => $data['older_adult_id'],
            'presentation' => $data['presentation'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'minimum_stock' => $data['minimum_stock'],
            'expiration_date' => $data['expiration_date'],
            'is_active' => $data['is_active'] ?? true,
            'dosage' => $data['dosage'] ?? null,
            'schedule' => $data['schedule'] ?? null,
        ];
    }

    private function inventoryItems(?int $olderAdultId = null): array
    {
        return OlderAdultMedication::query()
            ->with(['medication', 'olderAdult'])
            ->withCount('administrations')
            ->when($olderAdultId, fn ($query) => $query->where('older_adult_id', $olderAdultId))
            ->orderBy('older_adult_id')
            ->orderBy('medication_id')
            ->get()
            ->map(fn (OlderAdultMedication $inventoryItem) => $this->formatInventoryItem($inventoryItem))
            ->values()
            ->all();
    }

    private function formatInventoryItem(OlderAdultMedication $inventoryItem): array
    {
        $status = $inventoryItem->inventoryStatus();

        return [
            'id' => $inventoryItem->id,
            'medication_id' => $inventoryItem->medication_id,
            'older_adult_id' => $inventoryItem->older_adult_id,
            'older_adult_name' => $inventoryItem->olderAdult?->full_name,
            'name' => $inventoryItem->medication?->name,
            'presentation' => $inventoryItem->presentation,
            'quantity' => (int) $inventoryItem->quantity,
            'unit' => $inventoryItem->unit,
            'minimum_stock' => (int) $inventoryItem->minimum_stock,
            'expiration_date' => $inventoryItem->expiration_date?->toDateString(),
            'is_active' => (bool) $inventoryItem->is_active,
            'dosage' => $inventoryItem->dosage,
            'schedule' => $inventoryItem->schedule,
            'status' => $status['key'],
            'status_label' => $status['label'],
            'assigned_patients' => 1,
            'active_assignments' => $inventoryItem->is_active ? 1 : 0,
            'administrations_count' => (int) ($inventoryItem->administrations_count ?? 0),
        ];
    }
}

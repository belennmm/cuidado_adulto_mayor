<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicationInventoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'inventory' => $this->inventoryItems(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateMedication($request);

        $medication = Medication::create($data);

        return response()->json([
            'message' => 'Medicamento agregado correctamente.',
            'medication' => $this->formatMedication($medication->fresh()),
        ], 201);
    }

    public function update(Request $request, Medication $medication): JsonResponse
    {
        $data = $this->validateMedication($request, $medication);

        $medication->update($data);

        return response()->json([
            'message' => 'Medicamento actualizado correctamente.',
            'medication' => $this->formatMedication($medication->fresh()),
        ]);
    }

    public function adjustStock(Request $request, Medication $medication): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:increase,decrease'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $amount = (int) $data['amount'];
        $currentQuantity = (int) $medication->quantity;
        $nextQuantity = $data['action'] === 'increase'
            ? $currentQuantity + $amount
            : $currentQuantity - $amount;

        if ($nextQuantity < 0) {
            return response()->json([
                'message' => 'La cantidad no puede quedar negativa.',
            ], 422);
        }

        $medication->update([
            'quantity' => $nextQuantity,
        ]);

        return response()->json([
            'message' => $data['action'] === 'increase'
                ? 'Stock aumentado correctamente.'
                : 'Stock reducido correctamente.',
            'medication' => $this->formatMedication($medication->fresh()),
        ]);
    }

    public function destroy(Medication $medication): JsonResponse
    {
        $hasAssignments = $medication->olderAdultMedications()->exists();
        $hasAdministrations = $medication->administrations()->exists();

        if ($hasAssignments || $hasAdministrations) {
            return response()->json([
                'message' => 'No se puede eliminar este medicamento porque ya tiene asignaciones o administraciones registradas.',
            ], 422);
        }

        $medication->delete();

        return response()->json([
            'message' => 'Medicamento eliminado correctamente.',
        ]);
    }

    private function validateMedication(Request $request, ?Medication $medication = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medications', 'name')->ignore($medication?->id),
            ],
            'presentation' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:80'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function inventoryItems(): array
    {
        return Medication::query()
            ->with(['olderAdultMedications'])
            ->withCount('administrations')
            ->orderBy('name')
            ->get()
            ->map(fn (Medication $medication) => $this->formatMedication($medication))
            ->values()
            ->all();
    }

    private function formatMedication(Medication $medication): array
    {
        $status = $medication->inventoryStatus();
        $activeAssignments = $medication->relationLoaded('olderAdultMedications')
            ? $medication->olderAdultMedications->filter(fn ($assignment) => (bool) $assignment->is_active)
            : collect();

        return [
            'id' => $medication->id,
            'name' => $medication->name,
            'presentation' => $medication->presentation,
            'quantity' => (int) $medication->quantity,
            'unit' => $medication->unit,
            'minimum_stock' => (int) $medication->minimum_stock,
            'expiration_date' => $medication->expiration_date?->toDateString(),
            'is_active' => (bool) $medication->is_active,
            'status' => $status['key'],
            'status_label' => $status['label'],
            'assigned_patients' => $activeAssignments
                ->pluck('older_adult_id')
                ->filter()
                ->unique()
                ->count(),
            'active_assignments' => $activeAssignments->count(),
            'administrations_count' => (int) ($medication->administrations_count ?? 0),
        ];
    }
}

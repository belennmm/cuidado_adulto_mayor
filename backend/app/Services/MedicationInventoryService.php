<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\OlderAdultMedication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MedicationInventoryService
{
    public function inventory(?int $olderAdultId = null): Collection
    {
        return OlderAdultMedication::query()
            ->with(['medication', 'olderAdult'])
            ->withCount('administrations')
            ->when($olderAdultId, fn ($query) => $query->where('older_adult_id', $olderAdultId))
            ->orderBy('older_adult_id')
            ->orderBy('medication_id')
            ->get();
    }

    public function create(array $data): OlderAdultMedication
    {
        $inventoryItem = DB::transaction(function () use ($data) {
            $medication = $this->medication($data['name']);

            return OlderAdultMedication::create([
                ...$this->assignmentData($data),
                'medication_id' => $medication->id,
            ]);
        });

        return $this->loadRelations($inventoryItem);
    }

    public function update(OlderAdultMedication $inventoryItem, array $data): OlderAdultMedication
    {
        DB::transaction(function () use ($inventoryItem, $data) {
            $medication = $this->medication($data['name']);
            $inventoryItem->update([
                ...$this->assignmentData($data),
                'medication_id' => $medication->id,
            ]);
        });

        return $this->loadRelations($inventoryItem->refresh());
    }

    public function adjustStock(
        OlderAdultMedication $inventoryItem,
        string $action,
        int $amount,
    ): OlderAdultMedication {
        $currentQuantity = (int) $inventoryItem->quantity;
        $nextQuantity = $action === 'increase'
            ? $currentQuantity + $amount
            : $currentQuantity - $amount;

        if ($nextQuantity < 0) {
            abort(response()->json(['message' => 'La cantidad no puede quedar negativa.'], 422));
        }

        $inventoryItem->update(['quantity' => $nextQuantity]);

        return $this->loadRelations($inventoryItem->refresh());
    }

    public function delete(OlderAdultMedication $inventoryItem): void
    {
        if ($inventoryItem->administrations()->exists()) {
            abort(response()->json([
                'message' => 'No se puede eliminar este inventario porque tiene administraciones registradas.',
            ], 422));
        }

        $inventoryItem->delete();
    }

    private function medication(string $name): Medication
    {
        return Medication::firstOrCreate(
            ['name' => trim($name)],
            ['is_active' => true],
        );
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

    private function loadRelations(OlderAdultMedication $inventoryItem): OlderAdultMedication
    {
        return $inventoryItem->load(['medication', 'olderAdult']);
    }
}

<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\MedicationAcquisition;
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
            $inventoryItem = OlderAdultMedication::create([
                ...$this->assignmentData($data),
                'medication_id' => $medication->id,
            ]);

            $quantity = (int) $inventoryItem->quantity;
            if ($quantity > 0) {
                MedicationAcquisition::create([
                    'medication_id' => $medication->id,
                    'older_adult_id' => $inventoryItem->older_adult_id,
                    'quantity' => $quantity,
                    'acquired_at' => now(config('app.timezone')),
                ]);
            }

            return $inventoryItem;
        });

        return $this->loadRelations($inventoryItem);
    }

    public function update(OlderAdultMedication $inventoryItem, array $data): OlderAdultMedication
    {
        $requestedAdultId = $data['older_adult_id'] ?? null;
        $currentAdultId = $inventoryItem->older_adult_id;
        $sameLocation = $requestedAdultId === null
            ? $currentAdultId === null
            : (int) $requestedAdultId === (int) $currentAdultId;

        if (! $sameLocation) {
            abort(response()->json([
                'message' => 'La ubicación del stock no puede cambiarse al editarlo. Usa una transferencia de inventario cuando esté disponible.',
            ], 422));
        }

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
        $updatedItem = DB::transaction(function () use ($inventoryItem, $action, $amount) {
            $lockedItem = OlderAdultMedication::query()
                ->lockForUpdate()
                ->findOrFail($inventoryItem->getKey());
            $currentQuantity = (int) $lockedItem->quantity;
            $nextQuantity = $action === 'increase'
                ? $currentQuantity + $amount
                : $currentQuantity - $amount;

            if ($nextQuantity < 0) {
                abort(response()->json(['message' => 'La cantidad no puede quedar negativa.'], 422));
            }

            $lockedItem->update(['quantity' => $nextQuantity]);

            if ($action === 'increase') {
                MedicationAcquisition::create([
                    'medication_id' => $lockedItem->medication_id,
                    'older_adult_id' => $lockedItem->older_adult_id,
                    'quantity' => $amount,
                    'acquired_at' => now(config('app.timezone')),
                ]);
            }

            return $lockedItem;
        });

        return $this->loadRelations($updatedItem->refresh());
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
            'older_adult_id' => $data['older_adult_id'] ?? null,
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

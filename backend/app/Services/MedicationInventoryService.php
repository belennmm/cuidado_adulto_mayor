<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\MedicationAcquisition;
use App\Models\OlderAdultMedication;
use App\Support\ListPage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MedicationInventoryService
{
    public function inventory(?int $olderAdultId = null, ?array $input = null): Collection|LengthAwarePaginator
    {
        $query = OlderAdultMedication::query()
            ->with(['medication', 'olderAdult'])
            ->withCount('administrations')
            ->when($olderAdultId, fn ($query) => $query->where('older_adult_id', $olderAdultId))
            ->orderBy('older_adult_id')
            ->orderBy('medication_id')->orderBy('id');
        if (($input['scope'] ?? null) === 'unassigned') {
            $query->whereNull('older_adult_id');
        }

        return $input === null ? $query->get() : ListPage::query($query, $input);
    }

    public function consolidatedInventory(array $input): LengthAwarePaginator
    {
        $today = now(config('app.timezone'))->toDateString();
        $soon = now(config('app.timezone'))->addDays(30)->toDateString();
        $query = OlderAdultMedication::query()
            ->select('medication_id', 'unit')
            ->selectRaw('SUM(quantity) AS quantity, SUM(minimum_stock) AS minimum_stock')
            ->selectRaw('MIN(presentation) AS presentation')
            ->selectRaw('SUM(CASE WHEN older_adult_id IS NULL THEN quantity ELSE 0 END) AS unassigned_quantity')
            ->selectRaw('SUM(CASE WHEN older_adult_id IS NOT NULL THEN quantity ELSE 0 END) AS assigned_quantity')
            ->selectRaw('MIN(CASE WHEN older_adult_id IS NULL THEN id ELSE NULL END) AS unassigned_inventory_id')
            ->selectRaw('MIN(expiration_date) AS first_expiration, MAX(expiration_date) AS last_expiration, COUNT(*) - COUNT(expiration_date) AS undated_count')
            ->selectRaw("MIN(CASE WHEN expiration_date < ? THEN 'expired' WHEN expiration_date <= ? THEN 'expiring_soon' WHEN quantity <= minimum_stock THEN 'low_stock' ELSE 'available' END) AS first_status", [$today, $soon])
            ->selectRaw("MAX(CASE WHEN expiration_date < ? THEN 'expired' WHEN expiration_date <= ? THEN 'expiring_soon' WHEN quantity <= minimum_stock THEN 'low_stock' ELSE 'available' END) AS last_status", [$today, $soon])
            ->with('medication:id,name')
            ->groupBy('medication_id', 'unit')->orderBy('medication_id')->orderBy('unit');
        $page = ListPage::query($query, $input);
        $labels = ['expired' => 'Vencido', 'expiring_soon' => 'Próximo a vencer', 'low_stock' => 'Bajo stock', 'available' => 'Disponible', 'mixed' => 'Estados variados'];
        $page->setCollection($page->getCollection()->map(function ($item) use ($labels) {
            $status = $item->first_status === $item->last_status ? $item->first_status : 'mixed';
            $expiration = $item->first_expiration !== $item->last_expiration
                ? 'Vencimientos variados'
                : ($item->first_expiration
                    ? ((int) $item->undated_count > 0 ? 'Algunos sin fecha · ' : '').$item->first_expiration
                    : 'Sin fecha registrada');

            return [
                'id' => 'aggregate-'.$item->medication_id.':'.$item->unit,
                'medication_id' => $item->medication_id, 'name' => $item->medication?->name,
                'presentation' => $item->presentation,
                'unit' => $item->unit, 'is_consolidated' => true,
                'quantity' => (int) $item->quantity, 'minimum_stock' => (int) $item->minimum_stock,
                'assigned_quantity' => (int) $item->assigned_quantity,
                'unassigned_quantity' => (int) $item->unassigned_quantity,
                'unassigned_inventory_id' => $item->unassigned_inventory_id === null ? null : (int) $item->unassigned_inventory_id,
                'status' => $status, 'status_label' => $labels[$status], 'expiration_summary' => $expiration,
            ];
        }));

        return $page;
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

            if ($nextQuantity > 4294967295) {
                abort(response()->json(['message' => 'La cantidad excede el limite del inventario.'], 422));
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

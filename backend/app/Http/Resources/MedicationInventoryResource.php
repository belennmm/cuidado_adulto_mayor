<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->inventoryStatus();

        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'older_adult_id' => $this->older_adult_id,
            'older_adult_name' => $this->olderAdult?->full_name,
            'name' => $this->medication?->name,
            'presentation' => $this->presentation,
            'quantity' => (int) $this->quantity,
            'unit' => $this->unit,
            'minimum_stock' => (int) $this->minimum_stock,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'dosage' => $this->dosage,
            'schedule' => $this->schedule,
            'status' => $status['key'],
            'status_label' => $status['label'],
            'assigned_patients' => 1,
            'active_assignments' => $this->is_active ? 1 : 0,
            'administrations_count' => (int) ($this->administrations_count ?? 0),
        ];
    }
}

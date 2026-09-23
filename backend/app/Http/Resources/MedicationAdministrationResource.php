<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class MedicationAdministrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'older_adult_medication_id' => $this->older_adult_medication_id,
            'older_adult_id' => $this->older_adult_id,
            'medication_id' => $this->medication_id,
            'administration_date' => Carbon::parse($this->administration_date)->toDateString(),
            'administration_time' => $this->administration_time,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
        ];
    }
}

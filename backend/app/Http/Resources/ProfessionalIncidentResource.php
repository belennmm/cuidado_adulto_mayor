<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'older_adult_id' => $this->older_adult_id,
            'title' => $this->title,
            'description' => $this->description,
            'incident_date' => $this->incident_date?->toDateString(),
            'incident_time' => $this->incident_time,
            'severity' => $this->severity,
            'status' => $this->status,
        ];
    }
}

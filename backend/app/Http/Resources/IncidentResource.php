<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'adult_name' => $this->adult_name ?? $this->olderAdult?->full_name,
            'older_adult_id' => $this->older_adult_id ?? $this->olderAdult?->id,
            'severity' => $this->severity,
            'status' => $this->status,
            'incident_date' => $this->incident_date?->toDateString(),
            'incident_time' => $this->incident_time,
            'reported_by' => $this->reporter?->name,
            'reporter' => $this->reporter ? [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
                'email' => $this->reporter->email,
            ] : null,
            'older_adult' => $this->olderAdult ? [
                'id' => $this->olderAdult->id,
                'full_name' => $this->olderAdult->full_name,
                'age' => $this->olderAdult->age,
                'room' => $this->olderAdult->room,
                'status' => $this->olderAdult->status,
                'family_caregiver_id' => $this->olderAdult->family_caregiver_id,
                'family_caregiver_name' => $this->olderAdult->familyCaregiver?->name
                    ?? $this->olderAdult->caregiver_family,
                'professional_caregiver_name' => $this->olderAdult->professionalCaregiver?->name,
            ] : null,
        ];
    }
}

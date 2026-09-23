<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OlderAdultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'age' => $this->age,
            'birthdate' => $this->birthdate?->toDateString(),
            'gender' => $this->gender,
            'room' => $this->room,
            'status' => $this->status,
            'caregiver_family' => $this->caregiver_family,
            'family_caregiver_id' => $this->family_caregiver_id,
            'family_caregiver_name' => $this->familyCaregiver?->name,
            'professional_caregiver_id' => $this->professional_caregiver_id,
            'professional_caregiver_name' => $this->professionalCaregiver?->name,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'allergies' => $this->allergies,
            'medical_history' => $this->medical_history,
            'notes' => $this->notes,
            'medications' => $this->relationLoaded('medicationAssignments')
                ? $this->medicationAssignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'medication_id' => $assignment->medication_id,
                    'name' => $assignment->medication?->name,
                    'presentation' => $assignment->presentation,
                    'quantity' => (int) $assignment->quantity,
                    'unit' => $assignment->unit,
                    'minimum_stock' => (int) $assignment->minimum_stock,
                    'expiration_date' => $assignment->expiration_date?->toDateString(),
                    'dosage' => $assignment->dosage,
                    'schedule' => $assignment->schedule,
                    'days' => $assignment->days ?? [],
                    'notes' => $assignment->notes,
                    'is_active' => $assignment->is_active,
                ])->values()->all()
                : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

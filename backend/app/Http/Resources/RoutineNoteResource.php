<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutineNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'older_adult_id' => $this->older_adult_id,
            'content' => $this->content,
            'note_date' => $this->note_date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'professional_caregiver' => $this->professionalCaregiver ? [
                'id' => $this->professionalCaregiver->id,
                'name' => $this->professionalCaregiver->name,
            ] : null,
        ];
    }
}

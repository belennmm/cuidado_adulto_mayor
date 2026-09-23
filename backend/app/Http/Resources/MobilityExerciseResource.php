<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobilityExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'focus' => $this->focus,
            'duration_minutes' => $this->duration_minutes,
            'duration' => "{$this->duration_minutes} ".($this->duration_minutes === 1 ? 'minuto' : 'minutos'),
            'repetitions' => $this->repetitions,
            'instructions' => $this->instructions ?? [],
            'precaution' => $this->precaution,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

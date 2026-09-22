<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adulto_mayor_id' => $this->older_adult_id,
            'older_adult_id' => $this->older_adult_id,
            'nombre' => $this->nombre,
            'horario' => $this->horario,
            'actividades' => $this->actividades ?? [],
            'actividades_completadas' => $this->actividades_completadas ?? [],
            'completada' => (bool) $this->completada,
            'completada_at' => $this->completada_at?->toISOString(),
            'created_by' => $this->created_by,
            'adulto_mayor' => $this->olderAdult ? [
                'id' => $this->olderAdult->id,
                'full_name' => $this->olderAdult->full_name,
                'room' => $this->olderAdult->room,
                'status' => $this->olderAdult->status,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

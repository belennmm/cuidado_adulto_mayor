<?php

namespace App\Http\Requests;

class UpdateRoutineRequest extends RoutineRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'horario' => ['required', 'date_format:H:i'],
            'actividades' => ['required', 'array', 'min:1'],
            'actividades.*' => ['required', 'string', 'max:255'],
        ];
    }
}

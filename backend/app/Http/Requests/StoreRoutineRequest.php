<?php

namespace App\Http\Requests;

class StoreRoutineRequest extends RoutineRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'horario' => ['required', 'date_format:H:i'],
            'actividades' => ['required', 'array', 'min:1'],
            'actividades.*' => ['required', 'string', 'max:255'],
            'adulto_mayor_id' => ['required_without:older_adult_id', 'integer'],
            'older_adult_id' => ['required_without:adulto_mayor_id', 'integer'],
        ];
    }
}

<?php

namespace App\Http\Requests;

class CompleteRoutineActivityRequest extends RoutineRequest
{
    public function rules(): array
    {
        return [
            'actividad' => ['required_without:actividad_index', 'string', 'max:255', 'prohibits:actividad_index'],
            'actividad_index' => ['required_without:actividad', 'integer', 'min:0', 'max:99', 'prohibits:actividad'],
        ];
    }
}

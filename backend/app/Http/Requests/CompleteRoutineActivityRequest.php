<?php

namespace App\Http\Requests;

class CompleteRoutineActivityRequest extends RoutineRequest
{
    public function rules(): array
    {
        return [
            'actividad' => ['required_without:actividad_index', 'string', 'max:255'],
            'actividad_index' => ['required_without:actividad', 'integer', 'min:0'],
        ];
    }
}

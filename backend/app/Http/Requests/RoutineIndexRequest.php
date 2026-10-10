<?php

namespace App\Http\Requests;

class RoutineIndexRequest extends RoutineRequest
{
    public function rules(): array
    {
        return [
            'adulto_mayor_id' => ['nullable', 'integer', 'min:1'],
            'older_adult_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\ListPage;

class RoutineIndexRequest extends RoutineRequest
{
    public function rules(): array
    {
        return [
            ...ListPage::rules(),
            'adulto_mayor_id' => ['nullable', 'integer', 'min:1'],
            'older_adult_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

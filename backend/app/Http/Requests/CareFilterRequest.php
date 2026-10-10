<?php

namespace App\Http\Requests;

class CareFilterRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'older_adult_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CareFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'older_adult_id' => ['nullable', 'integer'],
        ];
    }
}

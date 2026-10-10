<?php

namespace App\Http\Requests;

class MedicationAdministrationRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'administration_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

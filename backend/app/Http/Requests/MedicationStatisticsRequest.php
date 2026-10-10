<?php

namespace App\Http\Requests;

class MedicationStatisticsRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['filter' => ['nullable', 'in:day,month,year']];
    }
}

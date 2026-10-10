<?php

namespace App\Http\Requests;

use App\Services\MedicationStatisticsService;
use Illuminate\Validation\Rule;

class MedicationStatisticsRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['filter' => ['nullable', 'string', Rule::in(MedicationStatisticsService::FILTERS)]];
    }
}

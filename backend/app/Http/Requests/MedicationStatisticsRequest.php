<?php

namespace App\Http\Requests;

use App\Validation\MedicationStatisticsFilter;
use App\Support\ListPage;
use Illuminate\Validation\Rule;

class MedicationStatisticsRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [...ListPage::rules(), 'filter' => ['nullable', 'string', Rule::in(MedicationStatisticsFilter::VALUES)]];
    }
}

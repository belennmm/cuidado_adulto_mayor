<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class MedicationInventoryRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => ['older_adult_id' => ['nullable', 'integer', 'min:1', Rule::exists('older_adults', 'id')]],
            'adjustStock' => ['action' => ['required', 'in:increase,decrease'], 'amount' => ['required', 'integer', 'min:1', 'max:4294967295']],
            'store', 'update' => [
                'older_adult_id' => ['nullable', 'integer', 'min:1', Rule::exists('older_adults', 'id')],
                'name' => ['required', 'string', 'max:255'],
                'presentation' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'unit' => ['required', 'string', 'max:80'], 'minimum_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'expiration_date' => ['nullable', 'date_format:Y-m-d'], 'is_active' => ['sometimes', 'boolean'],
                'dosage' => ['nullable', 'string', 'max:255'], 'schedule' => ['nullable', 'string', 'max:255'],
            ],
            default => [
                'older_adult_id' => ['nullable', 'integer', 'min:1', Rule::exists('older_adults', 'id')],
            ],
        };
    }
}

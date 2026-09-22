<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicationInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => ['older_adult_id' => ['nullable', 'integer', Rule::exists('older_adults', 'id')]],
            'adjustStock' => ['action' => ['required', 'in:increase,decrease'], 'amount' => ['required', 'integer', 'min:1']],
            default => [
                'older_adult_id' => ['required', 'integer', Rule::exists('older_adults', 'id')], 'name' => ['required', 'string', 'max:255'],
                'presentation' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'integer', 'min:0'],
                'unit' => ['required', 'string', 'max:80'], 'minimum_stock' => ['required', 'integer', 'min:0'],
                'expiration_date' => ['required', 'date'], 'is_active' => ['sometimes', 'boolean'],
                'dosage' => ['nullable', 'string', 'max:255'], 'schedule' => ['nullable', 'string', 'max:255'],
            ],
        };
    }
}

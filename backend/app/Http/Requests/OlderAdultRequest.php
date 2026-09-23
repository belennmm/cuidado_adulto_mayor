<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OlderAdultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'], 'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'birthdate' => ['nullable', 'date'], 'gender' => ['nullable', 'string', 'max:255'], 'room' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'], 'caregiver_family' => ['nullable', 'string', 'max:255'],
            'family_caregiver_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'familiar')->where('is_approved', true))],
            'professional_caregiver_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'profesional')->where('is_approved', true))],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'], 'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'allergies' => ['nullable', 'string', 'max:255'], 'medical_history' => ['nullable', 'string'], 'notes' => ['nullable', 'string'],
            'medications' => ['nullable', 'array'], 'medications.*.id' => ['nullable', 'integer', 'exists:older_adult_medications,id'],
            'medications.*.name' => ['required', 'string', 'max:255'], 'medications.*.presentation' => ['nullable', 'string', 'max:255'],
            'medications.*.quantity' => ['nullable', 'integer', 'min:0'], 'medications.*.unit' => ['nullable', 'string', 'max:80'],
            'medications.*.minimum_stock' => ['nullable', 'integer', 'min:0'], 'medications.*.expiration_date' => ['nullable', 'date'],
            'medications.*.dosage' => ['nullable', 'string', 'max:255'], 'medications.*.schedule' => ['nullable', 'string', 'max:255'],
            'medications.*.days' => ['nullable', 'array'], 'medications.*.days.*' => ['string', 'max:50'], 'medications.*.notes' => ['nullable', 'string'],
        ];
    }
}

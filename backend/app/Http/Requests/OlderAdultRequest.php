<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class OlderAdultRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'], 'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'birthdate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1800-01-01'], 'gender' => ['nullable', 'string', 'regex:/\A(?:femenino|masculino|otro)\z/iu'], 'room' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'regex:/\A(?:estable|atenci[oó]n|cr[ií]tico|en observaci[oó]n)\z/iu'], 'caregiver_family' => ['nullable', 'string', 'max:255'],
            'family_caregiver_id' => ['nullable', 'integer', 'min:1', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'familiar')->where('is_approved', true))],
            'professional_caregiver_id' => ['nullable', 'integer', 'min:1', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'profesional')->where('is_approved', true))],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'], 'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'allergies' => ['nullable', 'string', 'max:255'], 'medical_history' => ['nullable', 'string', 'max:10000'], 'notes' => ['nullable', 'string', 'max:10000'],
            'medications' => ['nullable', 'array', 'list', 'max:100'], 'medications.*.id' => ['nullable', 'integer', 'min:1'],
            'medications.*.name' => ['required', 'string', 'max:255'], 'medications.*.presentation' => ['nullable', 'string', 'max:255'],
            'medications.*.quantity' => ['nullable', 'integer', 'min:0', 'max:4294967295'], 'medications.*.unit' => ['nullable', 'string', 'max:80'],
            'medications.*.minimum_stock' => ['nullable', 'integer', 'min:0', 'max:4294967295'], 'medications.*.expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'medications.*.dosage' => ['nullable', 'string', 'max:255'], 'medications.*.schedule' => ['nullable', 'string', 'max:255'],
            'medications.*.days' => ['nullable', 'array', 'list', 'max:7'], 'medications.*.days.*' => ['string', 'regex:/\A(?:lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo)\z/iu'], 'medications.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

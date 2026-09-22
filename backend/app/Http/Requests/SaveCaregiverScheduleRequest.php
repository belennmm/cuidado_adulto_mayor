<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SaveCaregiverScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->route()?->getActionMethod() === 'adminStore') {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', 'profesional')->where('is_approved', true),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'end_time debe ser mayor que start_time.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (isset($validator->failed()['end_time']['After'])) {
            throw new HttpResponseException(response()->json([
                'message' => 'El horario es inválido.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}

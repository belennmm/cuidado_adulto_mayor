<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ScheduleChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:500'],
        ];
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
                'message' => 'El horario es invalido.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}

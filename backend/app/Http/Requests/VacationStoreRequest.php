<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class VacationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = Str::of((string) $this->user()?->role)->ascii()->lower()->trim()->toString();

        return in_array($role, ['profesional', 'cuidador_profesional'], true) && (bool) $this->user()?->is_approved;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Solo cuidadores profesionales aprobados pueden solicitar vacaciones.'], 403));
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

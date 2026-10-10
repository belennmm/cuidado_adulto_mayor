<?php

namespace App\Http\Requests;

use App\Support\ResourceAccess;
use Illuminate\Http\Exceptions\HttpResponseException;

class VacationStoreRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (ResourceAccess::professional($this->user()));
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

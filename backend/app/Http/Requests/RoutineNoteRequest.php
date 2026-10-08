<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RoutineNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.'], 403));
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => ['older_adult_id' => ['required', 'integer']],
            'store' => ['older_adult_id' => ['required', 'integer'], 'content' => ['required', 'string']],
            default => ['content' => ['required', 'string']],
        };
    }
}

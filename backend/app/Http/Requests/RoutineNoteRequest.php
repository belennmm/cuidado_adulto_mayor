<?php

namespace App\Http\Requests;

use App\Support\ResourceAccess;
use Illuminate\Http\Exceptions\HttpResponseException;

class RoutineNoteRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (ResourceAccess::professional($this->user()));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.'], 403));
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => ['older_adult_id' => ['required', 'integer', 'min:1']],
            'store' => ['older_adult_id' => ['required', 'integer', 'min:1'], 'content' => ['required', 'string', 'max:5000']],
            default => ['content' => ['required', 'string', 'max:5000']],
        };
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class RoutineNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = Str::of((string) $this->user()?->role)->ascii()->lower()->trim()->toString();

        return in_array($role, ['profesional', 'cuidador_profesional'], true) && (bool) $this->user()?->is_approved;
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

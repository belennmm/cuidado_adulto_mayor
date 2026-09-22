<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ProfessionalIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = Str::of((string) $this->user()?->role)->ascii()->lower()->trim()->toString();

        return in_array($role, ['admin', 'administrador'], true)
            || (in_array($role, ['profesional', 'cuidador_profesional'], true) && (bool) $this->user()?->is_approved);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['message' => 'No tienes acceso para registrar incidentes.'], 403));
    }

    public function rules(): array
    {
        if ($this->route()?->getActionMethod() === 'store') {
            return [
                'older_adult_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'], 'severity' => ['nullable', 'in:baja,media,alta'],
                'incident_date' => ['nullable', 'date_format:Y-m-d'], 'incident_time' => ['nullable', 'date_format:H:i'],
            ];
        }

        return [
            'description' => ['nullable', 'string', 'max:2000'], 'severity' => ['nullable', 'in:baja,media,alta'],
            'status' => ['nullable', 'in:abierto,en_progreso,resuelto,cerrado'],
        ];
    }

    public function messages(): array
    {
        return [
            'older_adult_id.required' => 'Debes seleccionar un adulto mayor.', 'older_adult_id.integer' => 'El adulto mayor seleccionado no es válido.',
            'title.required' => 'El título del incidente es obligatorio.', 'title.string' => 'El título del incidente debe ser texto.',
            'title.max' => 'El título del incidente no puede superar 255 caracteres.', 'severity.in' => 'La severidad debe ser baja, media o alta.',
            'description.string' => $this->route()?->getActionMethod() === 'update' ? 'La nota debe ser texto.' : 'La descripción debe ser texto.',
            'description.max' => $this->route()?->getActionMethod() === 'update' ? 'La nota no puede superar 2000 caracteres.' : 'La descripción no puede superar 2000 caracteres.',
            'incident_date.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.', 'incident_time.date_format' => 'La hora debe tener el formato HH:MM.',
            'status.in' => 'El estado no es válido.',
        ];
    }
}

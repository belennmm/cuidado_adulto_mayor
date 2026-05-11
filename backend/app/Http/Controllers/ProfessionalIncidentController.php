<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\OlderAdult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessionalIncidentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureProfessionalCaregiver($user?->role, (bool) $user?->is_approved);

        $data = $request->validate([
            'older_adult_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'incident_date' => ['nullable', 'date_format:Y-m-d'],
            'incident_time' => ['nullable', 'date_format:H:i'],
        ], [
            'older_adult_id.required' => 'Debes seleccionar un adulto mayor.',
            'older_adult_id.integer' => 'El adulto mayor seleccionado no es válido.',
            'title.required' => 'El título del incidente es obligatorio.',
            'title.string' => 'El título del incidente debe ser texto.',
            'title.max' => 'El título del incidente no puede superar 255 caracteres.',
            'incident_date.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'incident_time.date_format' => 'La hora debe tener el formato HH:MM.',
        ]);

        $olderAdult = OlderAdult::query()->find((int) $data['older_adult_id']);
        if (!$olderAdult) {
            throw ValidationException::withMessages([
                'older_adult_id' => ['El adulto mayor seleccionado no existe.'],
            ]);
        }

        if ((int) $olderAdult->professional_caregiver_id !== (int) $user->id) {
            return response()->json([
                'message' => 'No tienes acceso para registrar incidentes de este adulto mayor.',
            ], 403);
        }

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);
        $date = $data['incident_date'] ?? $now->toDateString();
        $time = isset($data['incident_time'])
            ? Carbon::createFromFormat('H:i', $data['incident_time'], $timezone)->format('H:i:s')
            : $now->format('H:i:s');

        $title = trim((string) $data['title']);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['El título del incidente no puede estar vacío.'],
            ]);
        }

        $incident = Incident::create([
            'title' => $title,
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => $date,
            'incident_time' => $time,
            'reported_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Incidente registrado correctamente.',
            'incident' => [
                'id' => $incident->id,
                'older_adult_id' => $incident->older_adult_id,
                'title' => $incident->title,
                'incident_date' => $incident->incident_date?->toDateString(),
                'incident_time' => $incident->incident_time,
                'severity' => $incident->severity,
                'status' => $incident->status,
            ],
        ], 201);
    }

    private function ensureProfessionalCaregiver(mixed $role, bool $isApproved): void
    {
        $normalized = $this->normalizeText($role);

        if (!in_array($normalized, ['profesional', 'cuidador_profesional'], true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para registrar incidentes.',
            ], 403));
        }

        if (!$isApproved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para registrar incidentes.',
            ], 403));
        }
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }
}


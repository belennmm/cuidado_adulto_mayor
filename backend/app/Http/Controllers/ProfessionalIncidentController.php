<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfessionalIncidentRequest;
use App\Models\Incident;
use App\Models\OlderAdult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessionalIncidentController extends Controller
{
    public function store(ProfessionalIncidentRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureProfessionalCaregiver($user?->role, (bool) $user?->is_approved);

        $data = $request->validated();

        $olderAdult = OlderAdult::query()->find((int) $data['older_adult_id']);
        if (! $olderAdult) {
            throw ValidationException::withMessages([
                'older_adult_id' => ['El adulto mayor seleccionado no existe.'],
            ]);
        }

        if (! $this->isAdmin($user?->role) && (int) $olderAdult->professional_caregiver_id !== (int) $user->id) {
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
            'description' => $data['description'] ?? null,
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => $data['severity'] ?? 'media',
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
                'description' => $incident->description,
                'incident_date' => $incident->incident_date?->toDateString(),
                'incident_time' => $incident->incident_time,
                'severity' => $incident->severity,
                'status' => $incident->status,
            ],
        ], 201);
    }

    public function update(ProfessionalIncidentRequest $request, Incident $incident): JsonResponse
    {
        $user = $request->user();
        $this->ensureProfessionalCaregiver($user?->role, (bool) $user?->is_approved);

        $incident->load('olderAdult:id,professional_caregiver_id,full_name');

        if (! $incident->older_adult_id || ! $incident->olderAdult) {
            return response()->json([
                'message' => 'No se puede modificar este incidente.',
            ], 403);
        }

        if ((int) $incident->olderAdult->professional_caregiver_id !== (int) $user->id) {
            return response()->json([
                'message' => 'No tienes acceso para modificar este incidente.',
            ], 403);
        }

        $data = $request->validated();

        $incident->fill([
            'description' => array_key_exists('description', $data) ? $data['description'] : $incident->description,
            'severity' => $data['severity'] ?? $incident->severity,
            'status' => $data['status'] ?? $incident->status,
        ]);

        $incident->save();

        return response()->json([
            'message' => 'Incidente actualizado correctamente.',
            'incident' => [
                'id' => $incident->id,
                'older_adult_id' => $incident->older_adult_id,
                'title' => $incident->title,
                'description' => $incident->description,
                'incident_date' => $incident->incident_date?->toDateString(),
                'incident_time' => $incident->incident_time,
                'severity' => $incident->severity,
                'status' => $incident->status,
            ],
        ]);
    }

    private function ensureProfessionalCaregiver(mixed $role, bool $isApproved): void
    {
        $normalized = $this->normalizeText($role);

        if ($this->isAdmin($normalized)) {
            return;
        }

        if (! in_array($normalized, ['profesional', 'cuidador_profesional'], true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para registrar incidentes.',
            ], 403));
        }

        if (! $isApproved) {
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

    private function isAdmin(mixed $role): bool
    {
        return in_array($this->normalizeText($role), ['admin', 'administrador'], true);
    }
}

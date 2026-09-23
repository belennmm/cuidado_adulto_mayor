<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessionalIncidentService
{
    public function create(User $user, array $data): Incident
    {
        $olderAdult = $this->findOlderAdult((int) $data['older_adult_id']);

        if (! $this->isAdmin($user) && (int) $olderAdult->professional_caregiver_id !== (int) $user->id) {
            abort(response()->json([
                'message' => 'No tienes acceso para registrar incidentes de este adulto mayor.',
            ], 403));
        }

        $title = trim((string) $data['title']);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['El título del incidente no puede estar vacío.'],
            ]);
        }

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);

        return Incident::create([
            'title' => $title,
            'description' => $data['description'] ?? null,
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => $data['severity'] ?? 'media',
            'status' => 'abierto',
            'incident_date' => $data['incident_date'] ?? $now->toDateString(),
            'incident_time' => isset($data['incident_time'])
                ? Carbon::createFromFormat('H:i', $data['incident_time'], $timezone)->format('H:i:s')
                : $now->format('H:i:s'),
            'reported_by' => $user->id,
        ]);
    }

    public function update(User $user, Incident $incident, array $data): Incident
    {
        $incident->load('olderAdult:id,professional_caregiver_id,full_name');

        if (! $incident->older_adult_id || ! $incident->olderAdult) {
            abort(response()->json(['message' => 'No se puede modificar este incidente.'], 403));
        }

        if ((int) $incident->olderAdult->professional_caregiver_id !== (int) $user->id) {
            abort(response()->json([
                'message' => 'No tienes acceso para modificar este incidente.',
            ], 403));
        }

        $incident->update([
            'description' => array_key_exists('description', $data)
                ? $data['description']
                : $incident->description,
            'severity' => $data['severity'] ?? $incident->severity,
            'status' => $data['status'] ?? $incident->status,
        ]);

        return $incident;
    }

    private function findOlderAdult(int $olderAdultId): OlderAdult
    {
        $olderAdult = OlderAdult::query()->find($olderAdultId);

        if (! $olderAdult) {
            throw ValidationException::withMessages([
                'older_adult_id' => ['El adulto mayor seleccionado no existe.'],
            ]);
        }

        return $olderAdult;
    }

    private function isAdmin(User $user): bool
    {
        $role = Str::of((string) $user->role)->ascii()->lower()->trim()->toString();

        return in_array($role, ['admin', 'administrador'], true);
    }
}

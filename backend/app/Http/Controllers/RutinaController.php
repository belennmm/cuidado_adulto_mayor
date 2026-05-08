<?php

namespace App\Http\Controllers;

use App\Models\OlderAdult;
use App\Models\Rutina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RutinaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'horario' => 'required|string|max:255',
            'actividades' => 'required|array|min:1',
            'actividades.*' => 'required|string|max:255',
            'adulto_mayor_id' => 'required_without:older_adult_id|integer',
            'older_adult_id' => 'required_without:adulto_mayor_id|integer',
        ]);

        $olderAdultId = (int) ($data['adulto_mayor_id'] ?? $data['older_adult_id']);
        $olderAdult = OlderAdult::query()->find($olderAdultId);

        if (!$olderAdult) {
            throw ValidationException::withMessages([
                'adulto_mayor_id' => ['El adulto mayor seleccionado no existe.'],
            ]);
        }

        $nombre = trim((string) $data['nombre']);
        $horario = trim((string) $data['horario']);
        $actividades = $this->normalizeActividades($data['actividades']);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => ['El nombre de la rutina no puede estar vacio.'],
            ]);
        }

        if ($horario === '') {
            throw ValidationException::withMessages([
                'horario' => ['El horario de la rutina no puede estar vacio.'],
            ]);
        }

        if (count($actividades) === 0) {
            throw ValidationException::withMessages([
                'actividades' => ['Debes registrar al menos una actividad.'],
            ]);
        }

        $this->authorizeOlderAdult($request, $olderAdult);

        $rutina = Rutina::create([
            'older_adult_id' => $olderAdult->id,
            'created_by' => $request->user()?->id,
            'nombre' => $nombre,
            'horario' => $horario,
            'actividades' => $actividades,
        ]);

        $rutina->load('olderAdult:id,full_name,room,status');

        return response()->json([
            'message' => 'Rutina creada correctamente.',
            'rutina' => $this->formatRutina($rutina),
        ], 201);
    }

    private function authorizeOlderAdult(Request $request, OlderAdult $olderAdult): void
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (in_array($role, ['familiar', 'cuidador_familiar', 'profesional', 'cuidador_profesional'], true) && !(bool) $user?->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para crear rutinas.',
            ], 403));
        }

        if ($role === 'admin' || $role === 'administrador') {
            return;
        }

        if (($role === 'profesional' || $role === 'cuidador_profesional') && (int) $olderAdult->professional_caregiver_id === (int) $user?->id) {
            return;
        }

        if (($role === 'familiar' || $role === 'cuidador_familiar') && $this->isFamilyAssigned($olderAdult, $user)) {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes acceso a la informacion de este adulto mayor.',
        ], 403));
    }

    private function isFamilyAssigned(OlderAdult $olderAdult, mixed $user): bool
    {
        if ((int) $olderAdult->family_caregiver_id === (int) $user?->id) {
            return true;
        }

        return $olderAdult->family_caregiver_id === null
            && $this->normalizeText($olderAdult->caregiver_family) === $this->normalizeText($user?->name);
    }

    private function normalizeActividades(array $actividades): array
    {
        return collect($actividades)
            ->map(fn ($actividad) => trim((string) $actividad))
            ->filter(fn (string $actividad) => $actividad !== '')
            ->values()
            ->all();
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }

    private function formatRutina(Rutina $rutina): array
    {
        return [
            'id' => $rutina->id,
            'adulto_mayor_id' => $rutina->older_adult_id,
            'older_adult_id' => $rutina->older_adult_id,
            'nombre' => $rutina->nombre,
            'horario' => $rutina->horario,
            'actividades' => $rutina->actividades ?? [],
            'created_by' => $rutina->created_by,
            'adulto_mayor' => $rutina->olderAdult ? [
                'id' => $rutina->olderAdult->id,
                'full_name' => $rutina->olderAdult->full_name,
                'room' => $rutina->olderAdult->room,
                'status' => $rutina->olderAdult->status,
            ] : null,
            'created_at' => $rutina->created_at?->toISOString(),
            'updated_at' => $rutina->updated_at?->toISOString(),
        ];
    }
}

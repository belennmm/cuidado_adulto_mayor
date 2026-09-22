<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteRoutineActivityRequest;
use App\Http\Requests\RoutineIndexRequest;
use App\Http\Requests\StoreRoutineRequest;
use App\Http\Requests\UpdateRoutineRequest;
use App\Models\OlderAdult;
use App\Models\Rutina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RutinaController extends Controller
{
    public function index(RoutineIndexRequest $request): JsonResponse
    {
        $data = $request->validated();

        $olderAdultId = $data['adulto_mayor_id'] ?? $data['older_adult_id'] ?? null;
        $query = Rutina::query()->with('olderAdult:id,full_name,room,status');

        if ($olderAdultId !== null && $olderAdultId !== '') {
            $olderAdult = OlderAdult::query()->find((int) $olderAdultId);

            if (! $olderAdult) {
                throw ValidationException::withMessages([
                    'adulto_mayor_id' => ['El adulto mayor seleccionado no existe.'],
                ]);
            }

            $this->authorizeOlderAdult($request, $olderAdult);
            $query->where('older_adult_id', $olderAdult->id);
        } else {
            $this->scopeRutinasForUser($request, $query);
        }

        $rutinas = $query
            ->orderBy('horario')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Rutina $rutina) => $this->formatRutina($rutina))
            ->values();

        return response()->json([
            'rutinas' => $rutinas,
        ]);
    }

    public function store(StoreRoutineRequest $request): JsonResponse
    {
        $data = $request->validated();

        $olderAdultId = (int) ($data['adulto_mayor_id'] ?? $data['older_adult_id']);
        $olderAdult = OlderAdult::query()->find($olderAdultId);

        if (! $olderAdult) {
            throw ValidationException::withMessages([
                'adulto_mayor_id' => ['El adulto mayor seleccionado no existe.'],
            ]);
        }

        $nombre = trim((string) $data['nombre']);
        $horario = $this->normalizeHorario($data['horario']);
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

    public function update(UpdateRoutineRequest $request, Rutina $rutina): JsonResponse
    {
        $rutina->load('olderAdult:id,full_name,room,status,professional_caregiver_id,family_caregiver_id,caregiver_family');
        $this->authorizeOlderAdult($request, $rutina->olderAdult);

        $data = $request->validated();

        $nombre = trim((string) $data['nombre']);
        $horario = $this->normalizeHorario($data['horario']);
        $actividades = $this->normalizeActividades($data['actividades']);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => ['El nombre de la rutina no puede estar vacio.'],
            ]);
        }

        if (count($actividades) === 0) {
            throw ValidationException::withMessages([
                'actividades' => ['Debes registrar al menos una actividad.'],
            ]);
        }

        $rutina->update([
            'nombre' => $nombre,
            'horario' => $horario,
            'actividades' => $actividades,
            'actividades_completadas' => null,
            'completada' => false,
            'completada_at' => null,
        ]);

        $rutina->refresh()->load('olderAdult:id,full_name,room,status');

        return response()->json([
            'message' => 'Rutina actualizada correctamente.',
            'rutina' => $this->formatRutina($rutina),
        ]);
    }

    public function complete(CompleteRoutineActivityRequest $request, Rutina $rutina): JsonResponse
    {
        $rutina->load('olderAdult:id,full_name,room,status,professional_caregiver_id,family_caregiver_id,caregiver_family');
        $this->authorizeOlderAdult($request, $rutina->olderAdult);

        $data = $request->validated();

        [$activityIndex, $activityName] = $this->activityFromRequest($rutina, $data);
        $completedActivities = $rutina->actividades_completadas ?? [];

        $completedActivities[$activityIndex] = [
            'actividad' => $activityName,
            'completada' => true,
            'completada_at' => now()->toISOString(),
        ];

        $routineCompleted = $this->allActivitiesCompleted($rutina->actividades ?? [], $completedActivities);

        $rutina->update([
            'actividades_completadas' => $completedActivities,
            'completada' => $routineCompleted,
            'completada_at' => $routineCompleted ? ($rutina->completada_at ?? now()) : null,
        ]);

        $rutina->refresh()->load('olderAdult:id,full_name,room,status');

        return response()->json([
            'message' => 'Actividad marcada como completada.',
            'rutina' => $this->formatRutina($rutina),
        ]);
    }

    public function destroy(Request $request, Rutina $rutina): JsonResponse
    {
        $rutina->load('olderAdult:id,full_name,room,status,professional_caregiver_id,family_caregiver_id,caregiver_family');
        $this->authorizeOlderAdult($request, $rutina->olderAdult);

        $rutina->delete();

        return response()->json([
            'message' => 'Rutina eliminada correctamente.',
        ]);
    }

    private function scopeRutinasForUser(Request $request, $query): void
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (in_array($role, ['familiar', 'cuidador_familiar', 'profesional', 'cuidador_profesional'], true) && ! (bool) $user?->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para consultar rutinas.',
            ], 403));
        }

        if ($role === 'admin' || $role === 'administrador') {
            return;
        }

        if ($role === 'profesional' || $role === 'cuidador_profesional') {
            $query->whereHas('olderAdult', fn ($olderAdultQuery) => $olderAdultQuery
                ->where('professional_caregiver_id', $user?->id));

            return;
        }

        if ($role === 'familiar' || $role === 'cuidador_familiar') {
            $normalizedName = $this->normalizeText($user?->name);

            $query->whereHas('olderAdult', fn ($olderAdultQuery) => $olderAdultQuery
                ->where('family_caregiver_id', $user?->id)
                ->orWhere(function ($legacyQuery) use ($normalizedName) {
                    $legacyQuery
                        ->whereNull('family_caregiver_id')
                        ->whereRaw('LOWER(caregiver_family) = ?', [$normalizedName]);
                }));

            return;
        }

        abort(response()->json([
            'message' => 'No tienes acceso a la informacion de rutinas.',
        ], 403));
    }

    private function authorizeOlderAdult(Request $request, OlderAdult $olderAdult): void
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (in_array($role, ['familiar', 'cuidador_familiar', 'profesional', 'cuidador_profesional'], true) && ! (bool) $user?->is_approved) {
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

    private function activityFromRequest(Rutina $rutina, array $data): array
    {
        $activities = $rutina->actividades ?? [];

        if (array_key_exists('actividad_index', $data)) {
            $activityIndex = (int) $data['actividad_index'];

            if (! array_key_exists($activityIndex, $activities)) {
                throw ValidationException::withMessages([
                    'actividad_index' => ['La actividad seleccionada no existe en esta rutina.'],
                ]);
            }

            return [$activityIndex, (string) $activities[$activityIndex]];
        }

        $requestedActivity = trim((string) ($data['actividad'] ?? ''));

        foreach ($activities as $index => $activity) {
            if ($this->normalizeText($activity) === $this->normalizeText($requestedActivity)) {
                return [(int) $index, (string) $activity];
            }
        }

        throw ValidationException::withMessages([
            'actividad' => ['La actividad seleccionada no existe en esta rutina.'],
        ]);
    }

    private function allActivitiesCompleted(array $activities, array $completedActivities): bool
    {
        if (count($activities) === 0) {
            return false;
        }

        foreach (array_keys($activities) as $index) {
            if (! ((bool) ($completedActivities[$index]['completada'] ?? false))) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHorario(string $horario): string
    {
        return Carbon::createFromFormat('H:i', trim($horario))->format('H:i');
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
            'actividades_completadas' => $rutina->actividades_completadas ?? [],
            'completada' => (bool) $rutina->completada,
            'completada_at' => $rutina->completada_at?->toISOString(),
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

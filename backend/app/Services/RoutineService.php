<?php

namespace App\Services;

use App\Models\OlderAdult;
use App\Models\Rutina;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoutineService
{
    public function listFor(User $user, ?int $olderAdultId = null): Collection
    {
        Gate::forUser($user)->authorize('viewAny', Rutina::class);
        $query = Rutina::query()->with('olderAdult:id,full_name,room,status');

        if ($olderAdultId !== null) {
            Gate::forUser($user)->authorize('accessOlderAdult', [Rutina::class, $this->findOlderAdult($olderAdultId)]);
            $query->where('older_adult_id', $olderAdultId);
        } else {
            $this->scopeForUser($query, $user);
        }

        return $query->orderBy('horario')->orderBy('nombre')->get();
    }

    public function findOlderAdult(int $id): OlderAdult
    {
        $olderAdult = OlderAdult::query()->find($id);

        if (! $olderAdult) {
            abort(404);
        }

        return $olderAdult;
    }

    public function create(array $data, OlderAdult $olderAdult, User $creator): Rutina
    {
        Gate::forUser($creator)->authorize('accessOlderAdult', [Rutina::class, $olderAdult]);
        $routine = Rutina::create([
            'older_adult_id' => $olderAdult->id,
            'created_by' => $creator->id,
            ...$this->normalizedRoutineData($data),
        ]);

        return $this->loadForResponse($routine);
    }

    public function update(Rutina $routine, array $data): Rutina
    {
        $routine->update([
            ...$this->normalizedRoutineData($data),
            'actividades_completadas' => null,
            'completada' => false,
            'completada_at' => null,
        ]);

        return $this->loadForResponse($routine->refresh());
    }

    public function completeActivity(Rutina $routine, array $data): Rutina
    {
        [$activityIndex, $activityName] = $this->activityFromData($routine, $data);
        $completedActivities = $routine->actividades_completadas ?? [];

        $completedActivities[$activityIndex] = [
            'actividad' => $activityName,
            'completada' => true,
            'completada_at' => now()->toISOString(),
        ];

        $routineCompleted = $this->allActivitiesCompleted(
            $routine->actividades ?? [],
            $completedActivities,
        );

        $routine->update([
            'actividades_completadas' => $completedActivities,
            'completada' => $routineCompleted,
            'completada_at' => $routineCompleted ? ($routine->completada_at ?? now()) : null,
        ]);

        return $this->loadForResponse($routine->refresh());
    }

    public function loadForAuthorization(Rutina $routine): Rutina
    {
        return $routine->load(
            'olderAdult:id,full_name,room,status,professional_caregiver_id,family_caregiver_id,caregiver_family',
        );
    }

    private function loadForResponse(Rutina $routine): Rutina
    {
        return $routine->load('olderAdult:id,full_name,room,status');
    }

    private function scopeForUser(Builder $query, User $user): void
    {
        if (Access::admin($user)) {
            return;
        }

        if (Access::professional($user)) {
            $query->whereHas('olderAdult', fn (Builder $olderAdultQuery) => $olderAdultQuery
                ->where('professional_caregiver_id', $user->id));

            return;
        }

        if (Access::family($user)) {
            $query->whereHas('olderAdult', fn (Builder $olderAdultQuery) => $olderAdultQuery
                ->where('family_caregiver_id', $user->id));

            return;
        }

        // Never return an unscoped query for a role without an explicit branch.
        $query->whereRaw('1 = 0');
    }

    private function normalizedRoutineData(array $data): array
    {
        $name = trim((string) $data['nombre']);
        $schedule = Carbon::createFromFormat('H:i', trim($data['horario']))->format('H:i');
        $activities = collect($data['actividades'])
            ->map(fn ($activity) => trim((string) $activity))
            ->filter(fn (string $activity) => $activity !== '')
            ->values()
            ->all();

        if ($name === '') {
            throw ValidationException::withMessages([
                'nombre' => ['El nombre de la rutina no puede estar vacio.'],
            ]);
        }

        if ($schedule === '') {
            throw ValidationException::withMessages([
                'horario' => ['El horario de la rutina no puede estar vacio.'],
            ]);
        }

        if ($activities === []) {
            throw ValidationException::withMessages([
                'actividades' => ['Debes registrar al menos una actividad.'],
            ]);
        }

        return ['nombre' => $name, 'horario' => $schedule, 'actividades' => $activities];
    }

    private function activityFromData(Rutina $routine, array $data): array
    {
        $activities = $routine->actividades ?? [];

        if (array_key_exists('actividad_index', $data)) {
            $index = (int) $data['actividad_index'];

            if (! array_key_exists($index, $activities)) {
                throw ValidationException::withMessages([
                    'actividad_index' => ['La actividad seleccionada no existe en esta rutina.'],
                ]);
            }

            return [$index, (string) $activities[$index]];
        }

        $requestedActivity = $this->normalizeText($data['actividad'] ?? '');

        foreach ($activities as $index => $activity) {
            if ($this->normalizeText($activity) === $requestedActivity) {
                return [(int) $index, (string) $activity];
            }
        }

        throw ValidationException::withMessages([
            'actividad' => ['La actividad seleccionada no existe en esta rutina.'],
        ]);
    }

    private function allActivitiesCompleted(array $activities, array $completedActivities): bool
    {
        if ($activities === []) {
            return false;
        }

        foreach (array_keys($activities) as $index) {
            if (! ((bool) ($completedActivities[$index]['completada'] ?? false))) {
                return false;
            }
        }

        return true;
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }
}

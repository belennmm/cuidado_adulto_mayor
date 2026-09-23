<?php

namespace App\Services;

use App\Models\MobilityExercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobilityExerciseService
{
    public function listFor(User $user, mixed $activeFilter = null, bool $hasActiveFilter = false): Collection
    {
        $this->authorizeRead($user);
        $query = MobilityExercise::query();

        if (! $this->isAdmin($user)) {
            $query->where('is_active', true);
        } elseif ($hasActiveFilter) {
            $active = filter_var($activeFilter, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($active === null) {
                throw ValidationException::withMessages([
                    'active' => ['El filtro active debe ser verdadero o falso.'],
                ]);
            }

            $query->where('is_active', $active);
        }

        return $query->orderBy('sort_order')->orderBy('title')->get();
    }

    public function accessibleExercise(User $user, MobilityExercise $exercise): MobilityExercise
    {
        $this->authorizeRead($user);

        if (! $exercise->is_active && ! $this->isAdmin($user)) {
            abort(404);
        }

        return $exercise;
    }

    public function create(array $data, User $actor): MobilityExercise
    {
        $instructions = $this->normalizeInstructions($data['instructions']);

        return MobilityExercise::create([
            ...$this->attributes($data, $instructions),
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title']),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function update(MobilityExercise $exercise, array $data, User $actor): MobilityExercise
    {
        $instructions = $this->normalizeInstructions($data['instructions']);
        $exercise->update([
            ...$this->attributes($data, $instructions),
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title'], $exercise->id),
            'updated_by' => $actor->id,
        ]);

        return $exercise->refresh();
    }

    private function attributes(array $data, array $instructions): array
    {
        return [
            'title' => trim($data['title']),
            'focus' => trim($data['focus']),
            'duration_minutes' => (int) $data['duration_minutes'],
            'repetitions' => trim($data['repetitions']),
            'instructions' => $instructions,
            'precaution' => trim($data['precaution']),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function normalizeInstructions(array $instructions): array
    {
        $normalized = collect($instructions)
            ->map(fn ($instruction) => trim((string) $instruction))
            ->filter()
            ->values()
            ->all();

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'instructions' => ['Debes registrar al menos una instrucción.'],
            ]);
        }

        return $normalized;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'ejercicio';
        $slug = $base;
        $suffix = 2;

        while (MobilityExercise::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function authorizeRead(User $user): void
    {
        $role = Str::lower((string) $user->role);

        if ($this->isAdmin($user)) {
            return;
        }

        if (in_array($role, ['profesional', 'cuidador_profesional'], true) && (bool) $user->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes permiso para consultar ejercicios de movilidad.',
        ], 403));
    }

    private function isAdmin(User $user): bool
    {
        return in_array(Str::lower((string) $user->role), ['admin', 'administrador'], true);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MobilityExercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobilityExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $query = MobilityExercise::query();

        if (! $this->isAdmin($request)) {
            $query->where('is_active', true);
        } elseif ($request->has('active')) {
            $active = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($active === null) {
                throw ValidationException::withMessages([
                    'active' => ['El filtro active debe ser verdadero o falso.'],
                ]);
            }

            $query->where('is_active', $active);
        }

        return response()->json([
            'exercises' => $query
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (MobilityExercise $exercise) => $this->formatExercise($exercise))
                ->values(),
        ]);
    }

    public function show(Request $request, MobilityExercise $mobilityExercise): JsonResponse
    {
        $this->authorizeRead($request);

        if (! $mobilityExercise->is_active && ! $this->isAdmin($request)) {
            abort(404);
        }

        return response()->json([
            'exercise' => $this->formatExercise($mobilityExercise),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $instructions = $this->normalizeInstructions($data['instructions']);
        $slug = $this->uniqueSlug($data['slug'] ?? $data['title']);

        $exercise = MobilityExercise::create([
            ...$this->attributes($data, $instructions),
            'slug' => $slug,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Ejercicio de movilidad creado correctamente.',
            'exercise' => $this->formatExercise($exercise),
        ], 201);
    }

    public function update(Request $request, MobilityExercise $mobilityExercise): JsonResponse
    {
        $data = $request->validate($this->rules($mobilityExercise), $this->messages());
        $instructions = $this->normalizeInstructions($data['instructions']);
        $slugSource = $data['slug'] ?? $data['title'];

        $mobilityExercise->update([
            ...$this->attributes($data, $instructions),
            'slug' => $this->uniqueSlug($slugSource, $mobilityExercise->id),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Ejercicio de movilidad actualizado correctamente.',
            'exercise' => $this->formatExercise($mobilityExercise->refresh()),
        ]);
    }

    public function destroy(MobilityExercise $mobilityExercise): JsonResponse
    {
        $mobilityExercise->delete();

        return response()->json([
            'message' => 'Ejercicio de movilidad eliminado correctamente.',
        ]);
    }

    private function rules(?MobilityExercise $exercise = null): array
    {
        return [
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('mobility_exercises', 'slug')->ignore($exercise?->id),
            ],
            'title' => 'required|string|max:255',
            'focus' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'repetitions' => 'required|string|max:255',
            'instructions' => 'required|array|min:1|max:20',
            'instructions.*' => 'required|string|max:1000',
            'precaution' => 'required|string|max:2000',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:65535',
        ];
    }

    private function messages(): array
    {
        return [
            'title.required' => 'El título del ejercicio es obligatorio.',
            'focus.required' => 'El área de enfoque es obligatoria.',
            'duration_minutes.required' => 'La duración es obligatoria.',
            'duration_minutes.min' => 'La duración debe ser de al menos un minuto.',
            'repetitions.required' => 'Las repeticiones son obligatorias.',
            'instructions.required' => 'Debes registrar las instrucciones.',
            'instructions.min' => 'Debes registrar al menos una instrucción.',
            'instructions.*.required' => 'Las instrucciones no pueden estar vacías.',
            'precaution.required' => 'La precaución es obligatoria.',
            'slug.unique' => 'Ya existe un ejercicio con este identificador.',
        ];
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
        $base = Str::slug($value);
        $base = $base !== '' ? $base : 'ejercicio';
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

    private function authorizeRead(Request $request): void
    {
        $user = $request->user();
        $role = Str::lower((string) $user?->role);

        if (in_array($role, ['admin', 'administrador'], true)) {
            return;
        }

        if (in_array($role, ['profesional', 'cuidador_profesional'], true) && (bool) $user?->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes permiso para consultar ejercicios de movilidad.',
        ], 403));
    }

    private function isAdmin(Request $request): bool
    {
        return in_array(Str::lower((string) $request->user()?->role), ['admin', 'administrador'], true);
    }

    private function formatExercise(MobilityExercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'slug' => $exercise->slug,
            'title' => $exercise->title,
            'focus' => $exercise->focus,
            'duration_minutes' => $exercise->duration_minutes,
            'duration' => "{$exercise->duration_minutes} ".($exercise->duration_minutes === 1 ? 'minuto' : 'minutos'),
            'repetitions' => $exercise->repetitions,
            'instructions' => $exercise->instructions ?? [],
            'precaution' => $exercise->precaution,
            'is_active' => (bool) $exercise->is_active,
            'sort_order' => $exercise->sort_order,
            'created_at' => $exercise->created_at?->toISOString(),
            'updated_at' => $exercise->updated_at?->toISOString(),
        ];
    }
}

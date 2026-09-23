<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteRoutineActivityRequest;
use App\Http\Requests\RoutineIndexRequest;
use App\Http\Requests\StoreRoutineRequest;
use App\Http\Requests\UpdateRoutineRequest;
use App\Http\Resources\RoutineResource;
use App\Models\Rutina;
use App\Services\RoutineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RutinaController extends Controller
{
    public function __construct(private readonly RoutineService $routineService) {}

    public function index(RoutineIndexRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Rutina::class);
        $data = $request->validated();
        $olderAdultId = $data['adulto_mayor_id'] ?? $data['older_adult_id'] ?? null;

        if ($olderAdultId !== null && $olderAdultId !== '') {
            $olderAdult = $this->routineService->findOlderAdult((int) $olderAdultId);
            Gate::forUser($request->user())->authorize('accessOlderAdult', [Rutina::class, $olderAdult]);
        }

        $routines = $this->routineService->listFor(
            $request->user(),
            $olderAdultId !== null && $olderAdultId !== '' ? (int) $olderAdultId : null,
        );

        return response()->json([
            'rutinas' => $routines
                ->map(fn (Rutina $routine) => RoutineResource::make($routine)->toArray($request))
                ->values(),
        ]);
    }

    public function store(StoreRoutineRequest $request): JsonResponse
    {
        $data = $request->validated();
        $olderAdult = $this->routineService->findOlderAdult(
            (int) ($data['adulto_mayor_id'] ?? $data['older_adult_id']),
        );
        Gate::forUser($request->user())->authorize('accessOlderAdult', [Rutina::class, $olderAdult]);

        $routine = $this->routineService->create($data, $olderAdult, $request->user());

        return response()->json([
            'message' => 'Rutina creada correctamente.',
            'rutina' => RoutineResource::make($routine)->toArray($request),
        ], 201);
    }

    public function update(UpdateRoutineRequest $request, Rutina $rutina): JsonResponse
    {
        $this->routineService->loadForAuthorization($rutina);
        Gate::forUser($request->user())->authorize('update', $rutina);
        $routine = $this->routineService->update($rutina, $request->validated());

        return response()->json([
            'message' => 'Rutina actualizada correctamente.',
            'rutina' => RoutineResource::make($routine)->toArray($request),
        ]);
    }

    public function complete(CompleteRoutineActivityRequest $request, Rutina $rutina): JsonResponse
    {
        $this->routineService->loadForAuthorization($rutina);
        Gate::forUser($request->user())->authorize('complete', $rutina);
        $routine = $this->routineService->completeActivity($rutina, $request->validated());

        return response()->json([
            'message' => 'Actividad marcada como completada.',
            'rutina' => RoutineResource::make($routine)->toArray($request),
        ]);
    }

    public function destroy(Request $request, Rutina $rutina): JsonResponse
    {
        $this->routineService->loadForAuthorization($rutina);
        Gate::forUser($request->user())->authorize('delete', $rutina);
        $rutina->delete();

        return response()->json(['message' => 'Rutina eliminada correctamente.']);
    }
}

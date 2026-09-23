<?php

namespace App\Http\Controllers;

use App\Http\Requests\MobilityExerciseRequest;
use App\Http\Resources\MobilityExerciseResource;
use App\Models\MobilityExercise;
use App\Services\MobilityExerciseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilityExerciseController extends Controller
{
    public function __construct(private readonly MobilityExerciseService $mobilityService) {}

    public function index(Request $request): JsonResponse
    {
        $exercises = $this->mobilityService->listFor(
            $request->user(),
            $request->query('active'),
            $request->has('active'),
        );

        return response()->json([
            'exercises' => $exercises
                ->map(fn (MobilityExercise $exercise) => $this->resource($exercise, $request))
                ->values(),
        ]);
    }

    public function show(Request $request, MobilityExercise $mobilityExercise): JsonResponse
    {
        $exercise = $this->mobilityService->accessibleExercise($request->user(), $mobilityExercise);

        return response()->json(['exercise' => $this->resource($exercise, $request)]);
    }

    public function store(MobilityExerciseRequest $request): JsonResponse
    {
        $exercise = $this->mobilityService->create($request->validated(), $request->user());

        return $this->exerciseResponse(
            'Ejercicio de movilidad creado correctamente.', $exercise, $request, 201,
        );
    }

    public function update(MobilityExerciseRequest $request, MobilityExercise $mobilityExercise): JsonResponse
    {
        $exercise = $this->mobilityService->update(
            $mobilityExercise, $request->validated(), $request->user(),
        );

        return $this->exerciseResponse(
            'Ejercicio de movilidad actualizado correctamente.', $exercise, $request,
        );
    }

    public function destroy(MobilityExercise $mobilityExercise): JsonResponse
    {
        $mobilityExercise->delete();

        return response()->json([
            'message' => 'Ejercicio de movilidad eliminado correctamente.',
        ]);
    }

    private function exerciseResponse(
        string $message,
        MobilityExercise $exercise,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'exercise' => $this->resource($exercise, $request),
        ], $status);
    }

    private function resource(MobilityExercise $exercise, Request $request): array
    {
        return MobilityExerciseResource::make($exercise)->toArray($request);
    }
}

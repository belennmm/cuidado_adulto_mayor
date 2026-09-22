<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutineNoteRequest;
use App\Http\Resources\RoutineNoteResource;
use App\Models\RoutineNote;
use App\Services\RoutineNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalRoutineNoteController extends Controller
{
    public function __construct(private readonly RoutineNoteService $routineNoteService) {}

    public function index(RoutineNoteRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->routineNoteService->authorize($user);
        $olderAdult = $this->routineNoteService->assignedOlderAdult(
            $user,
            (int) $request->validated('older_adult_id'),
        );
        [$weekStart, $weekEnd] = $this->routineNoteService->currentWeekRange();
        $notes = $this->routineNoteService->notesForCurrentWeek($user, $olderAdult);

        return response()->json([
            'older_adult' => [
                'id' => $olderAdult->id,
                'full_name' => $olderAdult->full_name,
                'room' => $olderAdult->room,
                'status' => $olderAdult->status,
            ],
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
            ],
            'notes' => $notes
                ->map(fn (RoutineNote $note) => RoutineNoteResource::make($note)->toArray($request))
                ->values(),
        ]);
    }

    public function store(RoutineNoteRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->routineNoteService->authorize($user);
        $data = $request->validated();
        $olderAdult = $this->routineNoteService->assignedOlderAdult($user, (int) $data['older_adult_id']);
        $note = $this->routineNoteService->create($user, $olderAdult, $data['content']);

        return $this->noteResponse('Nota guardada correctamente.', $note, $request, 201);
    }

    public function show(Request $request, RoutineNote $routineNote): JsonResponse
    {
        $this->routineNoteService->authorize($request->user());
        $note = $this->routineNoteService->ownedNote($request->user(), $routineNote);

        return response()->json([
            'note' => RoutineNoteResource::make($note)->toArray($request),
        ]);
    }

    public function update(RoutineNoteRequest $request, RoutineNote $routineNote): JsonResponse
    {
        $user = $request->user();
        $this->routineNoteService->authorize($user);
        $note = $this->routineNoteService->ownedNote($user, $routineNote);
        $note = $this->routineNoteService->update($note, $request->validated('content'));

        return $this->noteResponse('Nota actualizada correctamente.', $note, $request);
    }

    public function destroy(Request $request, RoutineNote $routineNote): JsonResponse
    {
        $this->routineNoteService->authorize($request->user());
        $note = $this->routineNoteService->ownedNote($request->user(), $routineNote);
        $note->delete();

        return response()->json(['message' => 'Nota eliminada correctamente.']);
    }

    private function noteResponse(
        string $message,
        RoutineNote $note,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'note' => RoutineNoteResource::make($note)->toArray($request),
        ], $status);
    }
}

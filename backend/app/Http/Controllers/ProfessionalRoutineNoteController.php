<?php

namespace App\Http\Controllers;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProfessionalRoutineNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->ensureProfessionalUser($request);

        $data = $request->validate([
            'older_adult_id' => 'required|integer',
        ]);

        $olderAdult = $this->assignedOlderAdultOrFail($user, (int) $data['older_adult_id']);
        [$weekStart, $weekEnd] = $this->currentWeekRange();

        $notes = RoutineNote::query()
            ->with('professionalCaregiver:id,name')
            ->where('older_adult_id', $olderAdult->id)
            ->whereBetween('note_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderByDesc('note_date')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (RoutineNote $note) => $this->formatNote($note))
            ->values();

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
            'notes' => $notes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->ensureProfessionalUser($request);

        $data = $request->validate([
            'older_adult_id' => 'required|integer',
            'content' => 'required|string',
        ]);

        $content = trim((string) $data['content']);
        if ($content === '') {
            return response()->json([
                'message' => 'La nota no puede estar vacia.',
            ], 422);
        }

        $olderAdult = $this->assignedOlderAdultOrFail($user, (int) $data['older_adult_id']);

        $note = RoutineNote::create([
            'older_adult_id' => $olderAdult->id,
            'professional_caregiver_id' => $user->id,
            'content' => $content,
            'note_date' => $this->today()->toDateString(),
        ]);

        $note->load('professionalCaregiver:id,name');

        return response()->json([
            'message' => 'Nota guardada correctamente.',
            'note' => $this->formatNote($note),
        ], 201);
    }

    public function update(Request $request, RoutineNote $routineNote): JsonResponse
    {
        $user = $this->ensureProfessionalUser($request);

        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $content = trim((string) $data['content']);
        if ($content === '') {
            return response()->json([
                'message' => 'La nota no puede estar vacia.',
            ], 422);
        }

        $this->assignedOlderAdultOrFail($user, $routineNote->older_adult_id);

        $routineNote->update([
            'content' => $content,
        ]);

        $routineNote->load('professionalCaregiver:id,name');

        return response()->json([
            'message' => 'Nota actualizada correctamente.',
            'note' => $this->formatNote($routineNote),
        ]);
    }

    public function destroy(Request $request, RoutineNote $routineNote): JsonResponse
    {
        $user = $this->ensureProfessionalUser($request);
        $this->assignedOlderAdultOrFail($user, $routineNote->older_adult_id);

        $routineNote->delete();

        return response()->json([
            'message' => 'Nota eliminada correctamente.',
        ]);
    }

    private function ensureProfessionalUser(Request $request): User
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (($role === 'profesional' || $role === 'cuidador_profesional') && (bool) $user?->is_approved) {
            return $user;
        }

        abort(response()->json([
            'message' => 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.',
        ], 403));
    }

    private function assignedOlderAdultOrFail(User $user, int $olderAdultId): OlderAdult
    {
        $olderAdult = OlderAdult::query()
            ->where('professional_caregiver_id', $user->id)
            ->whereKey($olderAdultId)
            ->first();

        if ($olderAdult) {
            return $olderAdult;
        }

        abort(response()->json([
            'message' => 'No tienes acceso a la informacion de este adulto mayor.',
        ], 403));
    }

    private function currentWeekRange(): array
    {
        $today = $this->today();

        return [
            $today->copy()->startOfWeek(Carbon::MONDAY),
            $today->copy()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }

    private function formatNote(RoutineNote $note): array
    {
        return [
            'id' => $note->id,
            'older_adult_id' => $note->older_adult_id,
            'content' => $note->content,
            'note_date' => $note->note_date?->toDateString(),
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
            'professional_caregiver' => $note->professionalCaregiver ? [
                'id' => $note->professionalCaregiver->id,
                'name' => $note->professionalCaregiver->name,
            ] : null,
        ];
    }
}

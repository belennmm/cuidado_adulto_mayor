<?php

namespace App\Services;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoutineNoteService
{
    public function __construct(private readonly CaregiverAccessService $caregiverAccess) {}

    public function authorize(User $user): void
    {
        $this->caregiverAccess->authorize($user, CaregiverAccessService::PROFESSIONAL);
    }

    public function assignedOlderAdult(User $user, int $olderAdultId): OlderAdult
    {
        return $this->caregiverAccess->assignedOlderAdultOrFail(
            $user,
            CaregiverAccessService::PROFESSIONAL,
            $olderAdultId,
        );
    }

    public function notesForCurrentWeek(User $user, OlderAdult $olderAdult): Collection
    {
        [$weekStart, $weekEnd] = $this->currentWeekRange();

        return RoutineNote::query()
            ->with('professionalCaregiver:id,name')
            ->where('older_adult_id', $olderAdult->id)
            ->where('professional_caregiver_id', $user->id)
            ->whereBetween('note_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderByDesc('note_date')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function create(User $user, OlderAdult $olderAdult, string $content): RoutineNote
    {
        $note = RoutineNote::create([
            'older_adult_id' => $olderAdult->id,
            'professional_caregiver_id' => $user->id,
            'content' => $this->normalizedContent($content),
            'note_date' => $this->today()->toDateString(),
        ]);

        return $this->loadCaregiver($note);
    }

    public function ownedNote(User $user, RoutineNote $note): RoutineNote
    {
        if ((int) $note->professional_caregiver_id !== (int) $user->id) {
            abort(response()->json(['message' => 'No tienes acceso a esta nota.'], 403));
        }

        $this->assignedOlderAdult($user, $note->older_adult_id);

        return $this->loadCaregiver($note);
    }

    public function update(RoutineNote $note, string $content): RoutineNote
    {
        $note->update(['content' => $this->normalizedContent($content)]);

        return $this->loadCaregiver($note);
    }

    public function currentWeekRange(): array
    {
        $today = $this->today();

        return [
            $today->copy()->startOfWeek(Carbon::MONDAY),
            $today->copy()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    private function normalizedContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            abort(response()->json(['message' => 'La nota no puede estar vacia.'], 422));
        }

        return $content;
    }

    private function loadCaregiver(RoutineNote $note): RoutineNote
    {
        return $note->load('professionalCaregiver:id,name');
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }
}

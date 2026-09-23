<?php

namespace App\Services;

use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReminderService
{
    public function __construct(private readonly CareDataService $careData) {}

    public function remindersFor(User $user): array
    {
        $this->authorize($user);
        $today = $this->careData->today();
        $assignments = $this->activeAssignments($user, $today);
        $administeredMap = $this->careData->administeredMedicationMap(
            $assignments->pluck('id')->filter()->values(),
            $today,
        );

        $reminders = $assignments
            ->filter(fn (OlderAdultMedication $assignment) => $this->isDueToday($assignment->days, $today))
            ->map(fn (OlderAdultMedication $assignment) => $this->formatReminder(
                $assignment,
                $administeredMap,
                $today,
            ))
            ->sortBy([
                ['administered_today', 'asc'],
                ['sort_order', 'asc'],
                ['older_adult_name', 'asc'],
            ])
            ->values()
            ->map(fn (array $entry) => $this->careData->withoutSortOrder($entry));

        return [
            'date' => $today->toDateString(),
            'summary' => [
                'total' => $reminders->count(),
                'pending' => $reminders->where('administered_today', false)->count(),
                'administered' => $reminders->where('administered_today', true)->count(),
            ],
            'reminders' => $reminders,
        ];
    }

    private function activeAssignments(User $user, Carbon $today): Collection
    {
        return OlderAdultMedication::query()
            ->with([
                'olderAdult:id,full_name,room,professional_caregiver_id',
                'medication:id,name',
            ])
            ->where('is_active', true)
            ->whereHas('olderAdult', fn ($query) => $query
                ->where('professional_caregiver_id', $user->id))
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $today->toDateString());
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today->toDateString());
            })
            ->get();
    }

    private function formatReminder(
        OlderAdultMedication $assignment,
        Collection $administeredMap,
        Carbon $today,
    ): array {
        $administeredTime = $administeredMap->get($assignment->id);

        return [
            'older_adult_medication_id' => $assignment->id,
            'older_adult_id' => $assignment->older_adult_id,
            'older_adult_name' => $assignment->olderAdult?->full_name,
            'room' => $assignment->olderAdult?->room,
            'medication_id' => $assignment->medication_id,
            'medication_name' => $assignment->medication?->name,
            'dosage' => $assignment->dosage,
            'schedule' => $assignment->schedule,
            'notes' => $assignment->notes,
            'due_today' => true,
            'administered_today' => $administeredTime !== null,
            'administered_time' => $administeredTime,
            'date' => $today->toDateString(),
            'sort_order' => $this->scheduleSortOrder($assignment->schedule),
        ];
    }

    private function isDueToday(mixed $days, Carbon $today): bool
    {
        if (! is_array($days) || $days === []) {
            return true;
        }

        $todayName = $this->normalizeText($this->spanishDayName($today));

        return collect($days)
            ->map(fn ($day) => $this->normalizeText($day))
            ->contains($todayName);
    }

    private function authorize(User $user): void
    {
        $role = $this->normalizeText($user->role);

        if (! in_array($role, ['profesional', 'cuidador_profesional'], true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para consultar recordatorios.',
            ], 403));
        }

        if (! $user->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para consultar recordatorios.',
            ], 403));
        }
    }

    private function spanishDayName(Carbon $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            default => 'domingo',
        };
    }

    private function scheduleSortOrder(?string $schedule): int
    {
        if (! preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', (string) $schedule, $matches)) {
            return 9999;
        }

        $hour = (int) $matches[1];
        $minutes = (int) $matches[2];
        $period = strtoupper($matches[3] ?? '');

        if ($period === 'PM' && $hour < 12) {
            $hour += 12;
        }

        if ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }

        return ($hour * 60) + $minutes;
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }
}

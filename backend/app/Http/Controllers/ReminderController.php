<?php

namespace App\Http\Controllers;

use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureProfessionalCaregiver($user?->role, (bool) $user?->is_approved);

        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $todayName = $this->spanishDayName($today);

        $assignments = OlderAdultMedication::query()
            ->with([
                'olderAdult:id,full_name,room,professional_caregiver_id',
                'medication:id,name',
            ])
            ->where('is_active', true)
            ->whereHas('olderAdult', fn ($query) => $query->where('professional_caregiver_id', $user->id))
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $today->toDateString());
            })
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today->toDateString());
            })
            ->get();

        $assignmentIds = $assignments->pluck('id')->filter()->values();
        $administeredMap = $this->administeredMedicationMap($assignmentIds, $today);

        $reminders = $assignments
            ->filter(fn (OlderAdultMedication $assignment) => $this->isDueToday($assignment->days, $todayName))
            ->map(function (OlderAdultMedication $assignment) use ($administeredMap, $today) {
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
            })
            ->sortBy([
                ['administered_today', 'asc'],
                ['sort_order', 'asc'],
                ['older_adult_name', 'asc'],
            ])
            ->values()
            ->map(fn (array $entry) => $this->withoutSortOrder($entry));

        return response()->json([
            'date' => $today->toDateString(),
            'summary' => [
                'total' => $reminders->count(),
                'pending' => $reminders->where('administered_today', false)->count(),
                'administered' => $reminders->where('administered_today', true)->count(),
            ],
            'reminders' => $reminders,
        ]);
    }

    private function administeredMedicationMap(Collection $assignmentIds, Carbon $today): Collection
    {
        if ($assignmentIds->isEmpty()) {
            return collect();
        }

        return MedicationAdministration::query()
            ->where('administration_type', 'scheduled')
            ->whereDate('administration_date', $today->toDateString())
            ->whereIn('older_adult_medication_id', $assignmentIds)
            ->whereNotNull('older_adult_medication_id')
            ->orderBy('administration_time')
            ->get()
            ->pluck('administration_time', 'older_adult_medication_id');
    }

    private function isDueToday(mixed $days, string $todayName): bool
    {
        if (!is_array($days) || count($days) === 0) {
            return true;
        }

        $todayNormalized = $this->normalizeText($todayName);

        return collect($days)
            ->map(fn ($day) => $this->normalizeText($day))
            ->contains($todayNormalized);
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
        if (!preg_match('/(\\d{1,2}):(\\d{2})\\s*(AM|PM)?/i', (string) $schedule, $matches)) {
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

    private function ensureProfessionalCaregiver(mixed $role, bool $isApproved): void
    {
        $normalized = $this->normalizeText($role);

        if (!in_array($normalized, ['profesional', 'cuidador_profesional'], true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para consultar recordatorios.',
            ], 403));
        }

        if (!$isApproved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para consultar recordatorios.',
            ], 403));
        }
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }

    private function withoutSortOrder(array $entry): array
    {
        unset($entry['sort_order']);

        return $entry;
    }
}


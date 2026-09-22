<?php

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CareDataService
{
    public function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }

    public function buildRoutine(Collection $olderAdults, Carbon $today, bool $onlyDueToday): Collection
    {
        $administeredMap = $this->administeredMedicationMap(
            $this->medicationAssignmentIds($olderAdults),
            $today
        );

        return $olderAdults
            ->flatMap(function (OlderAdult $olderAdult) use ($today, $onlyDueToday, $administeredMap) {
                return $olderAdult->medicationAssignments
                    ->filter(fn ($assignment) => (bool) $assignment->is_active)
                    ->map(function ($assignment) use ($olderAdult, $today, $administeredMap) {
                        $dueToday = $this->isDueToday($assignment->days, $today);
                        $administeredTime = $administeredMap->get($assignment->id);

                        return [
                            'id' => $assignment->id,
                            'older_adult_id' => $olderAdult->id,
                            'older_adult_name' => $olderAdult->full_name,
                            'room' => $olderAdult->room,
                            'status' => $olderAdult->status,
                            'medication_name' => $assignment->medication?->name,
                            'dosage' => $assignment->dosage,
                            'schedule' => $assignment->schedule,
                            'days' => $assignment->days ?? [],
                            'notes' => $assignment->notes,
                            'due_today' => $dueToday,
                            'administered_today' => $administeredTime !== null,
                            'administered_time' => $administeredTime,
                            'sort_order' => $this->scheduleSortOrder($assignment->schedule),
                        ];
                    })
                    ->filter(fn (array $entry) => ! $onlyDueToday || $entry['due_today']);
            })
            ->values();
    }

    public function statusSummary(Collection $olderAdults): array
    {
        $summary = ['stable' => 0, 'attention' => 0, 'critical' => 0];

        foreach ($olderAdults as $olderAdult) {
            $summary[$this->statusKey($olderAdult->status)]++;
        }

        return $summary;
    }

    public function formatOlderAdultSummary(OlderAdult $olderAdult): array
    {
        return [
            'id' => $olderAdult->id,
            'full_name' => $olderAdult->full_name,
            'age' => $olderAdult->age,
            'room' => $olderAdult->room,
            'status' => $olderAdult->status,
            'family_caregiver_id' => $olderAdult->family_caregiver_id,
            'family_caregiver_name' => $olderAdult->familyCaregiver?->name ?? $olderAdult->caregiver_family,
            'family_caregiver' => $this->formatCaregiver($olderAdult->familyCaregiver),
            'professional_caregiver_id' => $olderAdult->professional_caregiver_id,
            'professional_caregiver_name' => $olderAdult->professionalCaregiver?->name,
            'professional_caregiver' => $this->formatCaregiver($olderAdult->professionalCaregiver),
            'medications_count' => $olderAdult->medicationAssignments->where('is_active', true)->count(),
        ];
    }

    public function formatOlderAdultDetail(
        OlderAdult $olderAdult,
        Carbon $today,
        Collection $administeredMap,
        bool $includeTimestamps = false
    ): array {
        $detail = [
            ...$this->formatOlderAdultSummary($olderAdult),
            'birthdate' => $olderAdult->birthdate?->toDateString(),
            'gender' => $olderAdult->gender,
            'caregiver_family' => $olderAdult->caregiver_family,
            'emergency_contact_name' => $olderAdult->emergency_contact_name,
            'emergency_contact_phone' => $olderAdult->emergency_contact_phone,
            'allergies' => $olderAdult->allergies,
            'medical_history' => $olderAdult->medical_history,
            'notes' => $olderAdult->notes,
            'medications' => $olderAdult->medicationAssignments
                ->filter(fn ($assignment) => (bool) $assignment->is_active)
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'medication_id' => $assignment->medication_id,
                    'name' => $assignment->medication?->name,
                    'dosage' => $assignment->dosage,
                    'schedule' => $assignment->schedule,
                    'days' => $assignment->days ?? [],
                    'notes' => $assignment->notes,
                    'start_date' => $assignment->start_date?->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                    'is_active' => $assignment->is_active,
                    'due_today' => $this->isDueToday($assignment->days, $today),
                    'administered_today' => $administeredMap->has($assignment->id),
                    'administered_time' => $administeredMap->get($assignment->id),
                ])
                ->values(),
        ];

        if ($includeTimestamps) {
            $detail['created_at'] = $olderAdult->created_at?->toISOString();
            $detail['updated_at'] = $olderAdult->updated_at?->toISOString();
        }

        return $detail;
    }

    public function medicationAssignmentIds(Collection $olderAdults): Collection
    {
        return $olderAdults
            ->flatMap(fn (OlderAdult $olderAdult) => $olderAdult->medicationAssignments->pluck('id'))
            ->filter()
            ->values();
    }

    public function administeredMedicationMap(Collection $assignmentIds, Carbon $today): Collection
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

    public function withoutSortOrder(array $entry): array
    {
        unset($entry['sort_order']);

        return $entry;
    }

    public function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_approved' => $user->is_approved,
            'location' => $user->location,
            'phone' => $user->phone,
            'birthdate' => $user->birthdate?->toDateString(),
        ];
    }

    private function formatCaregiver(?User $caregiver): ?array
    {
        if (! $caregiver) {
            return null;
        }

        return [
            'id' => $caregiver->id,
            'name' => $caregiver->name,
            'email' => $caregiver->email,
            'phone' => $caregiver->phone,
            'location' => $caregiver->location,
        ];
    }

    private function isDueToday(mixed $days, Carbon $today): bool
    {
        if (! is_array($days) || count($days) === 0) {
            return true;
        }

        $todayName = $this->normalizeText($this->spanishDayName($today));

        return collect($days)
            ->map(fn ($day) => $this->normalizeText($day))
            ->contains($todayName);
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

    private function statusKey(?string $status): string
    {
        return match ($this->normalizeText($status)) {
            'estable' => 'stable',
            'atencion' => 'attention',
            default => 'critical',
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

<?php

namespace App\Http\Controllers;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProfessionalCareController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->ensureProfessionalUser($request);

        $today = $this->today();
        $olderAdults = $this->assignedOlderAdults($request->user())->get();
        $routineToday = $this->buildRoutine($olderAdults, $today, true);
        $incidentsToday = $this->incidentsForOlderAdults($olderAdults, $today);
        $statusSummary = $this->statusSummary($olderAdults);
        $schedules = $this->assignedSchedules($request->user())->get();

        return response()->json([
            'date' => $today->toDateString(),
            'user' => $this->formatUser($request->user()),
            'stats' => [
                'older_adults' => $olderAdults->count(),
                'stable' => $statusSummary['stable'],
                'attention' => $statusSummary['attention'],
                'critical' => $statusSummary['critical'],
                'medications_today' => $routineToday->count(),
                'pending_medications' => $routineToday->where('administered_today', false)->count(),
                'incidents_today' => $incidentsToday->count(),
                'schedules' => $schedules->count(),
            ],
            'older_adults' => $olderAdults
                ->take(4)
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultSummary($olderAdult))
                ->values(),
            'next_medications' => $routineToday
                ->sortBy('sort_order')
                ->take(4)
                ->values()
                ->map(fn (array $entry) => $this->withoutSortOrder($entry)),
            'incidents' => $incidentsToday->take(4)->values(),
            'schedules' => $schedules
                ->take(4)
                ->map(fn (CaregiverSchedule $schedule) => $this->formatSchedule($schedule))
                ->values(),
        ]);
    }

    public function olderAdults(Request $request): JsonResponse
    {
        $this->ensureProfessionalUser($request);

        $today = $this->today();
        $olderAdults = $this->assignedOlderAdults($request->user())->get();
        $administeredMap = $this->administeredMedicationMap($this->medicationAssignmentIds($olderAdults), $today);

        return response()->json([
            'date' => $today->toDateString(),
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultDetail($olderAdult, $today, $administeredMap))
                ->values(),
        ]);
    }

    public function olderAdult(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->ensureProfessionalUser($request);

        $today = $this->today();
        $assignedOlderAdult = $this->assignedOlderAdultOrFail($request->user(), $olderAdult->id);
        $olderAdults = collect([$assignedOlderAdult]);
        $administeredMap = $this->administeredMedicationMap($this->medicationAssignmentIds($olderAdults), $today);
        $incidents = $this->incidentsForOlderAdults($olderAdults);

        return response()->json([
            'date' => $today->toDateString(),
            'older_adult' => [
                ...$this->formatOlderAdultDetail($assignedOlderAdult, $today, $administeredMap),
                'incidents_count' => $incidents->count(),
                'last_incident' => $incidents->first(),
                'incidents' => $incidents,
            ],
        ]);
    }

    public function routine(Request $request): JsonResponse
    {
        $this->ensureProfessionalUser($request);

        $data = $request->validate([
            'older_adult_id' => 'nullable|integer',
        ]);

        $today = $this->today();
        $olderAdults = $this->olderAdultsForRequest($request, $data['older_adult_id'] ?? null);
        $routine = $this->buildRoutine($olderAdults, $today, false);
        $todayRoutine = $routine->where('due_today', true);

        return response()->json([
            'date' => $today->toDateString(),
            'summary' => [
                'total' => $routine->count(),
                'today' => $todayRoutine->count(),
                'administered_today' => $todayRoutine->where('administered_today', true)->count(),
                'pending_today' => $todayRoutine->where('administered_today', false)->count(),
            ],
            'routine' => $routine
                ->sortBy([
                    ['due_today', 'desc'],
                    ['sort_order', 'asc'],
                    ['older_adult_name', 'asc'],
                ])
                ->values()
                ->map(fn (array $entry) => $this->withoutSortOrder($entry)),
        ]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $this->ensureProfessionalUser($request);

        return response()->json([
            'schedules' => $this->assignedSchedules($request->user())
                ->get()
                ->map(fn (CaregiverSchedule $schedule) => $this->formatSchedule($schedule))
                ->values(),
        ]);
    }

    private function ensureProfessionalUser(Request $request): void
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (($role === 'profesional' || $role === 'cuidador_profesional') && (bool) $user?->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.',
        ], 403));
    }

    private function olderAdultsForRequest(Request $request, mixed $olderAdultId = null): Collection
    {
        if ($olderAdultId !== null && $olderAdultId !== '') {
            return collect([$this->assignedOlderAdultOrFail($request->user(), (int) $olderAdultId)]);
        }

        return $this->assignedOlderAdults($request->user())->get();
    }

    private function assignedOlderAdultOrFail(User $user, int $olderAdultId): OlderAdult
    {
        $olderAdult = $this->assignedOlderAdults($user)
            ->whereKey($olderAdultId)
            ->first();

        if ($olderAdult) {
            return $olderAdult;
        }

        abort(response()->json([
            'message' => 'No tienes acceso a la informacion de este adulto mayor.',
        ], 403));
    }

    private function assignedOlderAdults(User $user)
    {
        return OlderAdult::query()
            ->with([
                'medicationAssignments.medication',
                'familyCaregiver:id,name,email,phone,location',
                'professionalCaregiver:id,name,email,phone,location',
            ])
            ->where('professional_caregiver_id', $user->id)
            ->orderBy('full_name');
    }

    private function assignedSchedules(User $user)
    {
        return CaregiverSchedule::query()
            ->where('user_id', $user->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }

    private function buildRoutine(Collection $olderAdults, Carbon $today, bool $onlyDueToday): Collection
    {
        $administeredMap = $this->administeredMedicationMap($this->medicationAssignmentIds($olderAdults), $today);

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
                    ->filter(fn (array $entry) => !$onlyDueToday || $entry['due_today']);
            })
            ->values();
    }

    private function incidentsForOlderAdults(Collection $olderAdults, ?Carbon $date = null): Collection
    {
        $adultIds = $olderAdults->pluck('id')->filter()->values();

        if ($adultIds->isEmpty()) {
            return collect();
        }

        $query = Incident::query()
            ->with(['reporter:id,name,email', 'olderAdult.familyCaregiver:id,name,email,phone,location'])
            ->whereIn('older_adult_id', $adultIds);

        if ($date) {
            $query->whereDate('incident_date', $date->toDateString());
        }

        return $query
            ->orderByDesc('incident_date')
            ->orderByRaw('incident_time IS NULL')
            ->orderByDesc('incident_time')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Incident $incident) => $this->formatIncident($incident))
            ->values();
    }

    private function formatIncident(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'description' => $incident->description,
            'adult_name' => $incident->adult_name ?? $incident->olderAdult?->full_name,
            'older_adult_id' => $incident->older_adult_id,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'incident_date' => $incident->incident_date?->toDateString(),
            'incident_time' => $incident->incident_time,
            'reported_by' => $incident->reporter?->name,
            'older_adult' => $incident->olderAdult ? $this->formatOlderAdultSummary($incident->olderAdult) : null,
        ];
    }

    private function statusSummary(Collection $olderAdults): array
    {
        $summary = ['stable' => 0, 'attention' => 0, 'critical' => 0];

        foreach ($olderAdults as $olderAdult) {
            $summary[$this->statusKey($olderAdult->status)]++;
        }

        return $summary;
    }

    private function formatOlderAdultSummary(OlderAdult $olderAdult): array
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
            'medications_count' => $olderAdult->medicationAssignments->where('is_active', true)->count(),
        ];
    }

    private function formatOlderAdultDetail(OlderAdult $olderAdult, Carbon $today, Collection $administeredMap): array
    {
        return [
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
    }

    private function formatCaregiver(?User $caregiver): ?array
    {
        if (!$caregiver) {
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

    private function formatSchedule(CaregiverSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => $this->formatTime($schedule->start_time),
            'end_time' => $this->formatTime($schedule->end_time),
            'notes' => $schedule->notes,
            'change_request' => $schedule->change_request_status ? [
                'status' => $schedule->change_request_status,
                'start_time' => $this->formatTime($schedule->change_request_start_time),
                'end_time' => $this->formatTime($schedule->change_request_end_time),
                'notes' => $schedule->change_request_notes,
                'message' => $schedule->change_request_message,
            ] : null,
        ];
    }

    private function medicationAssignmentIds(Collection $olderAdults): Collection
    {
        return $olderAdults
            ->flatMap(fn (OlderAdult $olderAdult) => $olderAdult->medicationAssignments->pluck('id'))
            ->filter()
            ->values();
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

    private function isDueToday(mixed $days, Carbon $today): bool
    {
        if (!is_array($days) || count($days) === 0) {
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
        if (!preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', (string) $schedule, $matches)) {
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

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return Carbon::createFromFormat(
            strlen((string) $time) === 5 ? 'H:i' : 'H:i:s',
            (string) $time,
            (string) config('app.timezone'),
        )->format('H:i:s');
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }

    private function withoutSortOrder(array $entry): array
    {
        unset($entry['sort_order']);

        return $entry;
    }

    private function formatUser(User $user): array
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

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }
}

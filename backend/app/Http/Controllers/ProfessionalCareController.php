<?php

namespace App\Http\Controllers;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use App\Services\CareDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProfessionalCareController extends Controller
{
    public function __construct(private readonly CareDataService $careData) {}

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
        return $this->careData->buildRoutine($olderAdults, $today, $onlyDueToday);
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
        return $this->careData->statusSummary($olderAdults);
    }

    private function formatOlderAdultSummary(OlderAdult $olderAdult): array
    {
        return $this->careData->formatOlderAdultSummary($olderAdult);
    }

    private function formatOlderAdultDetail(OlderAdult $olderAdult, Carbon $today, Collection $administeredMap): array
    {
        return $this->careData->formatOlderAdultDetail($olderAdult, $today, $administeredMap);
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
        return $this->careData->medicationAssignmentIds($olderAdults);
    }

    private function administeredMedicationMap(Collection $assignmentIds, Carbon $today): Collection
    {
        return $this->careData->administeredMedicationMap($assignmentIds, $today);
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
        return $this->careData->withoutSortOrder($entry);
    }

    private function formatUser(User $user): array
    {
        return $this->careData->formatUser($user);
    }

    private function today(): Carbon
    {
        return $this->careData->today();
    }
}

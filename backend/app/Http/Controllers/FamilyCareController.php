<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use App\Services\CareDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FamilyCareController extends Controller
{
    public function __construct(private readonly CareDataService $careData) {}

    public function overview(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $today = $this->today();
        $olderAdults = $this->assignedOlderAdults($request->user())->get();
        $routineToday = $this->buildRoutine($olderAdults, $today, true);
        $incidentsToday = $this->todayIncidents($olderAdults, $today);
        $statusSummary = $this->statusSummary($olderAdults);

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
            ],
            'older_adults' => $olderAdults
                ->take(3)
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultSummary($olderAdult))
                ->values(),
            'next_medications' => $routineToday
                ->sortBy('sort_order')
                ->take(4)
                ->values()
                ->map(fn (array $entry) => $this->withoutSortOrder($entry)),
            'incidents' => $incidentsToday->take(4)->values(),
        ]);
    }

    public function olderAdults(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $today = $this->today();
        $olderAdults = $this->assignedOlderAdults($request->user())->get();
        $administeredMap = $this->administeredMedicationMap(
            $this->medicationAssignmentIds($olderAdults),
            $today
        );

        return response()->json([
            'date' => $today->toDateString(),
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultDetail($olderAdult, $today, $administeredMap))
                ->values(),
        ]);
    }

    public function olderAdult(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $today = $this->today();
        $assignedOlderAdult = $this->assignedOlderAdultOrFail($request->user(), $olderAdult->id);
        $olderAdults = collect([$assignedOlderAdult]);
        $administeredMap = $this->administeredMedicationMap(
            $this->medicationAssignmentIds($olderAdults),
            $today
        );
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
        $this->ensureFamilyUser($request);

        $data = $request->validate([
            'older_adult_id' => 'nullable|integer',
        ]);

        $today = $this->today();
        $olderAdults = $this->olderAdultsForFamilyRequest($request, $data['older_adult_id'] ?? null);
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

    public function incidents(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'older_adult_id' => 'nullable|integer',
        ]);

        $date = isset($data['date'])
            ? Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay()
            : $this->today();

        $olderAdults = $this->olderAdultsForFamilyRequest($request, $data['older_adult_id'] ?? null);
        $incidents = $this->incidentsForOlderAdults($olderAdults, $date);

        return response()->json([
            'date' => $date->toDateString(),
            'summary' => $this->incidentSummary($incidents),
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultSummary($olderAdult))
                ->values(),
            'incidents' => $incidents,
        ]);
    }

    public function olderAdultIncidents(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $date = isset($data['date'])
            ? Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay()
            : null;

        $assignedOlderAdult = $this->assignedOlderAdultOrFail($request->user(), $olderAdult->id);
        $olderAdults = collect([$assignedOlderAdult]);
        $incidents = $this->incidentsForOlderAdults($olderAdults, $date);

        return response()->json([
            'date' => $date?->toDateString(),
            'filters' => [
                'older_adult_id' => $assignedOlderAdult->id,
                'date' => $date?->toDateString(),
            ],
            'summary' => $this->incidentSummary($incidents),
            'older_adult' => $this->formatOlderAdultSummary($assignedOlderAdult),
            'incidents' => $incidents,
        ]);
    }

    private function ensureFamilyUser(Request $request): void
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (($role === 'familiar' || $role === 'cuidador_familiar') && (bool) $user?->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'Esta informacion solo esta disponible para cuidadores familiares.',
        ], 403));
    }

    private function olderAdultsForFamilyRequest(Request $request, mixed $olderAdultId = null): Collection
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
            ->where(function ($query) use ($user) {
                $query
                    ->where('family_caregiver_id', $user->id)
                    ->orWhere(function ($legacyQuery) use ($user) {
                        $legacyQuery
                            ->whereNull('family_caregiver_id')
                            ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $user->name)]);
                    });
            })
            ->orderBy('full_name');
    }

    private function buildRoutine(Collection $olderAdults, Carbon $today, bool $onlyDueToday): Collection
    {
        return $this->careData->buildRoutine($olderAdults, $today, $onlyDueToday);
    }

    private function todayIncidents(Collection $olderAdults, Carbon $today): Collection
    {
        return $this->incidentsForOlderAdults($olderAdults, $today);
    }

    private function incidentsForOlderAdults(Collection $olderAdults, ?Carbon $date = null): Collection
    {
        $adultIds = $olderAdults->pluck('id')->filter()->values();
        $adultNames = $olderAdults->pluck('full_name')->filter()->values();

        if ($adultIds->isEmpty() && $adultNames->isEmpty()) {
            return collect();
        }

        $query = Incident::query()
            ->with([
                'reporter:id,name,email',
                'olderAdult.familyCaregiver:id,name,email,phone,location',
                'olderAdult.professionalCaregiver:id,name,email,phone,location',
                'olderAdult.medicationAssignments.medication',
            ])
            ->where(function ($incidentQuery) use ($adultIds, $adultNames) {
                $incidentQuery->whereIn('older_adult_id', $adultIds);

                if ($adultNames->isNotEmpty()) {
                    $incidentQuery->orWhere(function ($legacyQuery) use ($adultNames) {
                        $legacyQuery
                            ->whereNull('older_adult_id')
                            ->whereIn('adult_name', $adultNames);
                    });
                }
            });

        if ($date) {
            $query->whereDate('incident_date', $date->toDateString());
        }

        return $query
            ->orderByDesc('incident_date')
            ->orderByRaw('incident_time IS NULL')
            ->orderByDesc('incident_time')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Incident $incident) => $this->formatIncident($incident, $olderAdults))
            ->values();
    }

    private function formatIncident(Incident $incident, Collection $olderAdults): array
    {
        $olderAdult = $incident->olderAdult
            ?? $olderAdults->first(
                fn (OlderAdult $adult) => $this->normalizeText($adult->full_name) === $this->normalizeText($incident->adult_name)
            );

        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'description' => $incident->description,
            'adult_name' => $incident->adult_name ?? $olderAdult?->full_name,
            'older_adult_id' => $incident->older_adult_id ?? $olderAdult?->id,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'incident_date' => $incident->incident_date?->toDateString(),
            'incident_time' => $incident->incident_time,
            'reported_by' => $incident->reporter?->name,
            'reporter' => $incident->reporter ? [
                'id' => $incident->reporter->id,
                'name' => $incident->reporter->name,
                'email' => $incident->reporter->email,
            ] : null,
            'older_adult' => $olderAdult ? $this->formatOlderAdultSummary($olderAdult) : null,
        ];
    }

    private function incidentSummary(Collection $incidents): array
    {
        return [
            'total' => $incidents->count(),
            'open' => $incidents
                ->filter(fn (array $incident) => ! in_array($this->normalizeText($incident['status']), ['cerrado', 'resuelto'], true))
                ->count(),
            'resolved' => $incidents
                ->filter(fn (array $incident) => in_array($this->normalizeText($incident['status']), ['cerrado', 'resuelto'], true))
                ->count(),
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
        return $this->careData->formatOlderAdultDetail($olderAdult, $today, $administeredMap, true);
    }

    private function medicationAssignmentIds(Collection $olderAdults): Collection
    {
        return $this->careData->medicationAssignmentIds($olderAdults);
    }

    private function administeredMedicationMap(Collection $assignmentIds, Carbon $today): Collection
    {
        return $this->careData->administeredMedicationMap($assignmentIds, $today);
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

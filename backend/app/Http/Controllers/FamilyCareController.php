<?php

namespace App\Http\Controllers;

use App\Http\Requests\CareFilterRequest;
use App\Http\Requests\DateFilterRequest;
use App\Models\OlderAdult;
use App\Services\CareDataService;
use App\Services\CaregiverAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FamilyCareController extends Controller
{
    public function __construct(
        private readonly CareDataService $careData,
        private readonly CaregiverAccessService $caregiverAccess,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $olderAdults = $this->assignedAdults($request);
        $routineToday = $this->careData->buildRoutine($olderAdults, $today, true);
        $incidentsToday = $this->caregiverAccess->incidentsFor($olderAdults, $today, true);
        $statusSummary = $this->careData->statusSummary($olderAdults);

        return response()->json([
            'date' => $today->toDateString(),
            'user' => $this->careData->formatUser($request->user()),
            'stats' => [
                'older_adults' => $olderAdults->count(),
                'stable' => $statusSummary['stable'],
                'attention' => $statusSummary['attention'],
                'critical' => $statusSummary['critical'],
                'medications_today' => $routineToday->count(),
                'pending_medications' => $routineToday->where('administered_today', false)->count(),
                'incidents_today' => $incidentsToday->count(),
            ],
            'older_adults' => $olderAdults->take(3)
                ->map(fn (OlderAdult $adult) => $this->careData->formatOlderAdultSummary($adult))->values(),
            'next_medications' => $routineToday->sortBy('sort_order')->take(4)->values()
                ->map(fn (array $entry) => $this->careData->withoutSortOrder($entry)),
            'incidents' => $incidentsToday->take(4)->values(),
        ]);
    }

    public function olderAdults(Request $request): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $olderAdults = $this->assignedAdults($request);
        $administeredMap = $this->careData->administeredMedicationMap(
            $this->careData->medicationAssignmentIds($olderAdults), $today,
        );

        return response()->json([
            'date' => $today->toDateString(),
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $adult) => $this->careData
                    ->formatOlderAdultDetail($adult, $today, $administeredMap, true))->values(),
        ]);
    }

    public function olderAdult(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $assignedAdult = $this->caregiverAccess->assignedOlderAdultOrFail(
            $request->user(), CaregiverAccessService::FAMILY, $olderAdult->id,
        );
        $olderAdults = collect([$assignedAdult]);
        $administeredMap = $this->careData->administeredMedicationMap(
            $this->careData->medicationAssignmentIds($olderAdults), $today,
        );
        $incidents = $this->caregiverAccess->incidentsFor($olderAdults, null, true);

        return response()->json([
            'date' => $today->toDateString(),
            'older_adult' => [
                ...$this->careData->formatOlderAdultDetail($assignedAdult, $today, $administeredMap, true),
                'incidents_count' => $incidents->count(),
                'last_incident' => $incidents->first(),
                'incidents' => $incidents,
            ],
        ]);
    }

    public function routine(CareFilterRequest $request): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $olderAdults = $this->filteredAdults($request, $request->validated('older_adult_id'));
        $routine = $this->careData->buildRoutine($olderAdults, $today, false);
        $todayRoutine = $routine->where('due_today', true);

        return response()->json([
            'date' => $today->toDateString(),
            'summary' => [
                'total' => $routine->count(),
                'today' => $todayRoutine->count(),
                'administered_today' => $todayRoutine->where('administered_today', true)->count(),
                'pending_today' => $todayRoutine->where('administered_today', false)->count(),
            ],
            'routine' => $routine->sortBy([
                ['due_today', 'desc'], ['sort_order', 'asc'], ['older_adult_name', 'asc'],
            ])->values()->map(fn (array $entry) => $this->careData->withoutSortOrder($entry)),
        ]);
    }

    public function incidents(CareFilterRequest $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validated();
        $date = isset($data['date'])
            ? Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay()
            : $this->careData->today();
        $olderAdults = $this->filteredAdults($request, $data['older_adult_id'] ?? null);
        $incidents = $this->caregiverAccess->incidentsFor($olderAdults, $date, true);

        return response()->json([
            'date' => $date->toDateString(),
            'summary' => $this->caregiverAccess->incidentSummary($incidents),
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $adult) => $this->careData->formatOlderAdultSummary($adult))->values(),
            'incidents' => $incidents,
        ]);
    }

    public function olderAdultIncidents(DateFilterRequest $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->authorize($request);
        $dateValue = $request->validated('date');
        $date = $dateValue
            ? Carbon::createFromFormat('Y-m-d', $dateValue, config('app.timezone'))->startOfDay()
            : null;
        $assignedAdult = $this->caregiverAccess->assignedOlderAdultOrFail(
            $request->user(), CaregiverAccessService::FAMILY, $olderAdult->id,
        );
        $incidents = $this->caregiverAccess->incidentsFor(collect([$assignedAdult]), $date, true);

        return response()->json([
            'date' => $date?->toDateString(),
            'filters' => ['older_adult_id' => $assignedAdult->id, 'date' => $date?->toDateString()],
            'summary' => $this->caregiverAccess->incidentSummary($incidents),
            'older_adult' => $this->careData->formatOlderAdultSummary($assignedAdult),
            'incidents' => $incidents,
        ]);
    }

    private function authorize(Request $request): void
    {
        $this->caregiverAccess->authorize($request->user(), CaregiverAccessService::FAMILY);
    }

    private function assignedAdults(Request $request)
    {
        return $this->caregiverAccess
            ->assignedOlderAdults($request->user(), CaregiverAccessService::FAMILY)->get();
    }

    private function filteredAdults(Request $request, mixed $olderAdultId)
    {
        return $this->caregiverAccess
            ->olderAdultsFor($request->user(), CaregiverAccessService::FAMILY, $olderAdultId);
    }
}

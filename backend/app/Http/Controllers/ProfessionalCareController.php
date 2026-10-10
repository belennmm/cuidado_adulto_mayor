<?php

namespace App\Http\Controllers;

use App\Http\Requests\CareFilterRequest;
use App\Http\Requests\EmptyInputRequest;
use App\Http\Requests\ListRequest;
use App\Support\ListPage;
use App\Models\CaregiverSchedule;
use App\Models\OlderAdult;
use App\Services\CareDataService;
use App\Services\CaregiverAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfessionalCareController extends Controller
{
    public function __construct(
        private readonly CareDataService $careData,
        private readonly CaregiverAccessService $caregiverAccess,
    ) {}

    public function overview(EmptyInputRequest $request): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $olderAdults = $this->assignedAdults($request);
        $routineToday = $this->careData->buildRoutine($olderAdults, $today, true);
        $incidentsToday = $this->caregiverAccess->incidentsFor($olderAdults, $today);
        $statusSummary = $this->careData->statusSummary($olderAdults);
        $schedules = $this->assignedSchedules($request)->get();

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
                'schedules' => $schedules->count(),
            ],
            'older_adults' => $olderAdults->take(4)
                ->map(fn (OlderAdult $adult) => $this->careData->formatOlderAdultSummary($adult))->values(),
            'next_medications' => $routineToday->sortBy('sort_order')->take(4)->values()
                ->map(fn (array $entry) => $this->careData->withoutSortOrder($entry)),
            'incidents' => $incidentsToday->take(4)->values(),
            'schedules' => $schedules->take(4)->map($this->formatSchedule(...))->values(),
        ]);
    }

    public function olderAdults(ListRequest $request): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $query = $this->caregiverAccess->assignedOlderAdults($request->user(), CaregiverAccessService::PROFESSIONAL);
        if ($search = $request->validated('q')) {
            $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }
        $page = ListPage::query($query->orderBy('id'), $request->validated());
        $olderAdults = $page->getCollection();
        $administeredMap = $this->careData->administeredMedicationMap(
            $this->careData->medicationAssignmentIds($olderAdults), $today,
        );

        return response()->json([
            'date' => $today->toDateString(),
            ...ListPage::payload($page, 'older_adults', fn (OlderAdult $adult) => $this->careData
                ->formatOlderAdultDetail($adult, $today, $administeredMap)),
        ]);
    }

    public function olderAdult(EmptyInputRequest $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $assignedAdult = $this->caregiverAccess->assignedOlderAdultOrFail(
            $request->user(), CaregiverAccessService::PROFESSIONAL, $olderAdult->id,
        );
        $olderAdults = collect([$assignedAdult]);
        $administeredMap = $this->careData->administeredMedicationMap(
            $this->careData->medicationAssignmentIds($olderAdults), $today,
        );
        $incidents = $this->caregiverAccess->incidentsFor($olderAdults, null, false, []);

        return response()->json([
            'date' => $today->toDateString(),
            'older_adult' => [
                ...$this->careData->formatOlderAdultDetail($assignedAdult, $today, $administeredMap),
                'incidents_count' => $incidents->total(),
                'last_incident' => $incidents->first(),
                ...ListPage::payload($incidents, 'incidents'),
            ],
        ]);
    }

    public function routine(CareFilterRequest $request): JsonResponse
    {
        $this->authorize($request);
        $today = $this->careData->today();
        $olderAdults = $this->caregiverAccess->olderAdultsFor(
            $request->user(), CaregiverAccessService::PROFESSIONAL, $request->validated('older_adult_id'),
        );
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
            ...ListPage::payload(ListPage::collection($routine->sortBy([
                ['due_today', 'desc'], ['sort_order', 'asc'], ['older_adult_name', 'asc'],
            ])->values(), $request->validated()), 'routine', fn (array $entry) => $this->careData->withoutSortOrder($entry)),
        ]);
    }

    public function schedules(ListRequest $request): JsonResponse
    {
        $this->authorize($request);

        return response()->json(ListPage::payload(ListPage::query($this->assignedSchedules($request)->orderBy('id'), $request->validated()), 'schedules', $this->formatSchedule(...)));
    }

    private function authorize(Request $request): void
    {
        $this->caregiverAccess->authorize($request->user(), CaregiverAccessService::PROFESSIONAL);
    }

    private function assignedAdults(Request $request)
    {
        return $this->caregiverAccess
            ->assignedOlderAdults($request->user(), CaregiverAccessService::PROFESSIONAL)->get();
    }

    private function assignedSchedules(Request $request)
    {
        return CaregiverSchedule::query()->where('user_id', $request->user()->id)
            ->orderBy('day_of_week')->orderBy('start_time');
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
}

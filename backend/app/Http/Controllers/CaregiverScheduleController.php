<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCaregiverScheduleRequest;
use App\Http\Requests\ScheduleCalendarRequest;
use App\Http\Requests\ScheduleChangeRequest;
use App\Http\Resources\CaregiverScheduleResource;
use App\Models\CaregiverSchedule;
use App\Services\CaregiverScheduleService;
use App\Services\ScheduleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CaregiverScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleCalendarService $calendarService,
        private readonly CaregiverScheduleService $scheduleService,
    ) {}

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'schedules' => CaregiverScheduleResource::collection($this->scheduleService->all())->resolve(),
        ]);
    }

    public function calendar(ScheduleCalendarRequest $request): JsonResponse
    {
        $data = $request->validated();
        $timezone = (string) config('app.timezone');
        $startDate = Carbon::createFromFormat('Y-m-d', $data['start_date'], $timezone)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $data['end_date'], $timezone)->startOfDay();

        return response()->json($this->calendarService->build($startDate, $endDate));
    }

    public function store(SaveCaregiverScheduleRequest $request): JsonResponse
    {
        $schedule = $this->scheduleService->saveForCaregiver($request->user(), $request->validated());

        return $this->scheduleResponse(
            'Horario guardado correctamente.', $schedule, $request, 201,
        );
    }

    public function adminStore(SaveCaregiverScheduleRequest $request): JsonResponse
    {
        $schedule = $this->scheduleService->saveForAdmin($request->validated());

        return $this->scheduleResponse(
            'Turno asignado correctamente.', $schedule, $request, 201,
        );
    }

    public function update(SaveCaregiverScheduleRequest $request, CaregiverSchedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->update($request->user(), $schedule, $request->validated());

        return $this->scheduleResponse('Horario actualizado correctamente.', $schedule, $request);
    }

    public function requestChange(ScheduleChangeRequest $request, CaregiverSchedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->requestChange(
            $request->user(), $schedule, $request->validated(),
        );

        return $this->scheduleResponse(
            'Solicitud de cambio enviada correctamente.', $schedule, $request,
        );
    }

    public function approveChangeRequest(Request $request, CaregiverSchedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->approveChange($schedule);

        return $this->scheduleResponse(
            'Solicitud aprobada y turno actualizado.', $schedule, $request,
        );
    }

    public function rejectChangeRequest(Request $request, CaregiverSchedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->rejectChange($schedule);

        return $this->scheduleResponse('Solicitud rechazada correctamente.', $schedule, $request);
    }

    public function destroy(CaregiverSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(['message' => 'Turno eliminado correctamente.']);
    }

    private function scheduleResponse(
        string $message,
        CaregiverSchedule $schedule,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'schedule' => CaregiverScheduleResource::make($schedule)->toArray($request),
        ], $status);
    }
}

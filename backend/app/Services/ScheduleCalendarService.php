<?php

namespace App\Services;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScheduleCalendarService
{
    public function build(Carbon $startDate, Carbon $endDate): array
    {
        $schedules = CaregiverSchedule::query()
            ->with('user:id,name,email,role,is_approved')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $caregiverIds = $schedules->pluck('user_id')->filter()->unique()->values();
        $olderAdultsByCaregiver = OlderAdult::query()
            ->select('id', 'full_name', 'room', 'status', 'professional_caregiver_id')
            ->whereIn('professional_caregiver_id', $caregiverIds)
            ->orderBy('full_name')
            ->get()
            ->groupBy('professional_caregiver_id');

        $vacationsByCaregiver = VacationRequest::query()
            ->where('status', 'approved')
            ->whereIn('user_id', $caregiverIds)
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->get()
            ->groupBy('user_id');

        $shifts = $this->buildShifts(
            $schedules,
            $olderAdultsByCaregiver,
            $vacationsByCaregiver,
            $startDate,
            $endDate
        );

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'shifts' => $shifts->values(),
            'events' => $this->buildEvents($shifts, $startDate, $endDate)->values(),
        ];
    }

    private function buildShifts(
        Collection $schedules,
        Collection $olderAdultsByCaregiver,
        Collection $vacationsByCaregiver,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $shifts = collect();

        foreach ($schedules as $schedule) {
            $dates = $this->matchingDates($schedule, $startDate, $endDate);
            $olderAdults = $olderAdultsByCaregiver->get($schedule->user_id, collect());
            $vacations = $vacationsByCaregiver->get($schedule->user_id, collect());

            foreach ($dates as $date) {
                $status = $this->shiftStatus($schedule, $date, $today, $vacations);
                $notes = $this->shiftNotes($schedule, $status, $vacations, $date);

                if ($olderAdults->isEmpty()) {
                    $shifts->push($this->formatShift($schedule, $date, null, $status, $notes));

                    continue;
                }

                foreach ($olderAdults as $olderAdult) {
                    $shifts->push($this->formatShift($schedule, $date, $olderAdult, $status, $notes));
                }
            }
        }

        return $shifts
            ->sortBy([
                ['date', 'asc'],
                ['start_time', 'asc'],
                ['caregiver_name', 'asc'],
                ['older_adult_name', 'asc'],
            ])
            ->values();
    }

    private function buildEvents(Collection $shifts, Carbon $startDate, Carbon $endDate): Collection
    {
        $shiftEvents = $shifts->map(fn (array $shift) => [
            'id' => 'shift-'.$shift['id'],
            'type' => 'shift',
            'title' => 'Turno: '.($shift['caregiver_name'] ?: 'Cuidador'),
            'date' => $shift['date'],
            'time' => $shift['start_time'],
            'person' => $shift['older_adult_name'],
            'status' => $shift['status'],
            'description' => trim(($shift['start_time'] ?? '').' - '.($shift['end_time'] ?? '')."\n".($shift['notes'] ?? '')),
        ]);

        $vacationEvents = VacationRequest::query()
            ->with('user:id,name,email')
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(fn (VacationRequest $request) => [
                'id' => 'vacation-'.$request->id,
                'type' => 'vacation',
                'title' => 'Vacaciones: '.($request->user?->name ?: 'Cuidador'),
                'date' => $request->start_date?->toDateString(),
                'time' => null,
                'person' => $request->user?->email,
                'status' => $request->status,
                'description' => trim(($request->reason ?: 'Sin motivo')."\n".$request->start_date?->toDateString().' - '.$request->end_date?->toDateString()),
            ]);

        $incidentEvents = Incident::query()
            ->with(['olderAdult:id,full_name,room', 'reporter:id,name'])
            ->whereDate('incident_date', '>=', $startDate->toDateString())
            ->whereDate('incident_date', '<=', $endDate->toDateString())
            ->orderBy('incident_date')
            ->orderByRaw('incident_time IS NULL')
            ->orderBy('incident_time')
            ->get()
            ->map(fn (Incident $incident) => [
                'id' => 'incident-'.$incident->id,
                'type' => 'incident',
                'title' => $incident->title ?: 'Incidente',
                'date' => $incident->incident_date?->toDateString(),
                'time' => $incident->incident_time,
                'person' => $incident->adult_name ?? $incident->olderAdult?->full_name,
                'status' => $incident->status,
                'description' => trim(($incident->description ?: 'Sin descripcion')."\nReportado por: ".($incident->reporter?->name ?: 'Sin registro')),
            ]);

        return $shiftEvents
            ->merge($vacationEvents)
            ->merge($incidentEvents)
            ->sortBy([
                ['date', 'asc'],
                ['time', 'asc'],
                ['type', 'asc'],
            ])
            ->values();
    }

    private function matchingDates(CaregiverSchedule $schedule, Carbon $startDate, Carbon $endDate): Collection
    {
        $dates = collect();
        $cursor = $startDate->copy();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            if ((int) $cursor->dayOfWeek === (int) $schedule->day_of_week) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }

    private function shiftStatus(
        CaregiverSchedule $schedule,
        Carbon $date,
        Carbon $today,
        Collection $vacations
    ): string {
        if ($this->isVacationDate($vacations, $date)) {
            return 'cancelled';
        }

        if ($schedule->change_request_status === 'pending') {
            return 'pending';
        }

        return $date->lt($today) ? 'completed' : 'assigned';
    }

    private function shiftNotes(
        CaregiverSchedule $schedule,
        string $status,
        Collection $vacations,
        Carbon $date
    ): ?string {
        if ($status === 'cancelled') {
            $vacation = $vacations->first(fn (VacationRequest $request) => $date->between(
                $request->start_date->copy()->startOfDay(),
                $request->end_date->copy()->startOfDay()
            ));

            return $vacation?->reason ?: 'Turno cancelado por vacaciones aprobadas.';
        }

        if ($status === 'pending') {
            return $schedule->change_request_message ?: $schedule->change_request_notes ?: $schedule->notes;
        }

        return $schedule->notes;
    }

    private function isVacationDate(Collection $vacations, Carbon $date): bool
    {
        return $vacations->contains(fn (VacationRequest $request) => $date->between(
            $request->start_date->copy()->startOfDay(),
            $request->end_date->copy()->startOfDay()
        ));
    }

    private function formatShift(
        CaregiverSchedule $schedule,
        Carbon $date,
        ?OlderAdult $olderAdult,
        string $status,
        ?string $notes
    ): array {
        return [
            'id' => implode('-', [$schedule->id, $date->toDateString(), $olderAdult?->id ?? 'none']),
            'schedule_id' => $schedule->id,
            'caregiver_id' => $schedule->user_id,
            'caregiver_name' => $schedule->user?->name,
            'caregiver_email' => $schedule->user?->email,
            'older_adult_id' => $olderAdult?->id,
            'older_adult_name' => $olderAdult?->full_name ?? 'Sin adulto mayor asignado',
            'older_adult_room' => $olderAdult?->room,
            'date' => $date->toDateString(),
            'day_of_week' => (int) $schedule->day_of_week,
            'start_time' => $this->formatTime($schedule->start_time),
            'end_time' => $this->formatTime($schedule->end_time),
            'status' => $status,
            'notes' => $notes,
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

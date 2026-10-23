<?php

namespace App\Transformers;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\VacationRequest;
use App\Support\ListPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ScheduleCalendarTransformer
{
    public function transform(
        Collection $shifts,
        Collection $vacations,
        Collection $incidents,
        Carbon $startDate,
        Carbon $endDate,
        array $input,
    ): array {
        $events = ListPage::payload(ListPage::collection($this->buildEvents($shifts, $vacations, $incidents)->values(), [
            ...$input, 'page' => $input['events_page'] ?? 1,
        ]), 'events');

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            ...ListPage::payload(ListPage::collection($shifts->values(), $input), 'shifts'),
            'events' => $events['events'], 'events_pagination' => $events['pagination'],
        ];
    }

    private function buildEvents(Collection $shifts, Collection $vacations, Collection $incidents): Collection
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

        $vacationEvents = $vacations
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

        $incidentEvents = $incidents
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

    public function shift(
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

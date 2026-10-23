<?php

namespace App\Services;

use App\Models\CaregiverSchedule;
use App\Models\VacationRequest;
use App\Queries\ScheduleCalendarQuery;
use App\Transformers\ScheduleCalendarTransformer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScheduleCalendarService
{
    public function __construct(
        private readonly ScheduleCalendarQuery $query,
        private readonly ScheduleCalendarTransformer $transformer,
    ) {}

    public function build(Carbon $startDate, Carbon $endDate, array $input = []): array
    {
        $schedules = $this->query->schedules();

        $caregiverIds = $schedules->pluck('user_id')->filter()->unique()->values();
        $olderAdultsByCaregiver = $this->query->olderAdultsByCaregiver($caregiverIds);

        $vacationsByCaregiver = $this->query->approvedVacationsByCaregiver($caregiverIds, $startDate, $endDate);

        $shifts = $this->buildShifts(
            $schedules,
            $olderAdultsByCaregiver,
            $vacationsByCaregiver,
            $startDate,
            $endDate
        );

        return $this->transformer->transform(
            $shifts,
            $this->query->vacationEvents($startDate, $endDate),
            $this->query->incidentEvents($startDate, $endDate),
            $startDate, $endDate, $input,
        );
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
                    $shifts->push($this->transformer->shift($schedule, $date, null, $status, $notes));

                    continue;
                }

                foreach ($olderAdults as $olderAdult) {
                    $shifts->push($this->transformer->shift($schedule, $date, $olderAdult, $status, $notes));
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

}

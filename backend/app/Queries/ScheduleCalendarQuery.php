<?php

namespace App\Queries;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ScheduleCalendarQuery
{
    public function schedules(): Collection
    {
        return CaregiverSchedule::query()
            ->with('user:id,name,email,role,is_approved')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function olderAdultsByCaregiver(Collection $caregiverIds): Collection
    {
        return OlderAdult::query()
            ->select('id', 'full_name', 'room', 'status', 'professional_caregiver_id')
            ->whereIn('professional_caregiver_id', $caregiverIds)
            ->orderBy('full_name')
            ->get()
            ->groupBy('professional_caregiver_id');
    }

    public function approvedVacationsByCaregiver(Collection $caregiverIds, Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->vacationsOverlapping($startDate, $endDate)
            ->where('status', 'approved')
            ->whereIn('user_id', $caregiverIds)
            ->get()
            ->groupBy('user_id');
    }

    public function vacationEvents(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->vacationsOverlapping($startDate, $endDate)
            ->with('user:id,name,email')
            ->orderBy('start_date')
            ->get();
    }

    public function incidentEvents(Carbon $startDate, Carbon $endDate): Collection
    {
        return Incident::query()
            ->with(['olderAdult:id,full_name,room', 'reporter:id,name'])
            ->whereDate('incident_date', '>=', $startDate->toDateString())
            ->whereDate('incident_date', '<=', $endDate->toDateString())
            ->orderBy('incident_date')
            ->orderByRaw('incident_time IS NULL')
            ->orderBy('incident_time')
            ->get();
    }

    private function vacationsOverlapping(Carbon $startDate, Carbon $endDate): Builder
    {
        return VacationRequest::query()
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString());
    }
}

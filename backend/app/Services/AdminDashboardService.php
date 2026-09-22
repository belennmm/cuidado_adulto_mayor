<?php

namespace App\Services;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function summary(): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $dueTodayAssignmentIds = $this->dueTodayAssignmentIds($today);
        $administeredTodayAssignmentIds = MedicationAdministration::query()
            ->where('administration_type', 'scheduled')
            ->whereDate('administration_date', $today->toDateString())
            ->whereNotNull('older_adult_medication_id')
            ->whereIn('older_adult_medication_id', $dueTodayAssignmentIds)
            ->distinct()
            ->pluck('older_adult_medication_id');

        $pendingUserRequests = User::query()
            ->where('role', '!=', 'admin')
            ->where('is_approved', false)
            ->count();
        $pendingVacationRequests = VacationRequest::query()->where('status', 'pending')->count();
        $pendingScheduleChanges = CaregiverSchedule::query()
            ->where('change_request_status', 'pending')
            ->count();

        return [
            'date' => $today->toDateString(),
            'stats' => [
                'older_adults' => OlderAdult::query()->count(),
                'caregivers' => User::query()
                    ->whereIn('role', ['profesional', 'cuidador_profesional'])
                    ->where('is_approved', true)
                    ->count(),
                'incidents_today' => Incident::query()
                    ->whereDate('incident_date', $today->toDateString())
                    ->count(),
                'requests' => $pendingUserRequests + $pendingVacationRequests + $pendingScheduleChanges,
            ],
            'medications' => [
                'due_today' => $dueTodayAssignmentIds->count(),
                'administered_today' => $administeredTodayAssignmentIds->count(),
                'pending_today' => $dueTodayAssignmentIds->diff($administeredTodayAssignmentIds)->count(),
            ],
            'report' => [
                'late_entries' => 0,
                'absences' => 0,
                'vacation_requests' => $pendingVacationRequests,
                'change_requests' => $pendingScheduleChanges,
                'pending_users' => $pendingUserRequests,
            ],
        ];
    }

    private function dueTodayAssignmentIds(Carbon $today): Collection
    {
        $todayName = match ($today->dayOfWeekIso) {
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            default => 'domingo',
        };

        return OlderAdultMedication::query()
            ->where('is_active', true)
            ->where(function ($query) use ($todayName) {
                $query->whereNull('days')
                    ->orWhereJsonLength('days', 0)
                    ->orWhereJsonContains('days', $todayName);
            })
            ->pluck('id');
    }
}

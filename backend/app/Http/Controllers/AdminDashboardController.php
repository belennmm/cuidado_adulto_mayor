<?php

namespace App\Http\Controllers;

use App\Models\MedicationAdministration;
use App\Models\Medication;
use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $todayName = $this->getSpanishDayName($today);

        $dueTodayAssignmentIds = OlderAdultMedication::query()
            ->where('is_active', true)
            ->where(function ($query) use ($todayName) {
                $query
                    ->whereNull('days')
                    ->orWhereRaw("json_array_length(COALESCE(days::json, '[]'::json)) = 0")
                    ->orWhereRaw('? = ANY (SELECT json_array_elements_text(days::json))', [$todayName]);
            })
            ->pluck('id');

        $administeredTodayAssignmentIds = MedicationAdministration::query()
            ->where('administration_type', 'scheduled')
            ->whereDate('administration_date', $today->toDateString())
            ->whereNotNull('older_adult_medication_id')
            ->whereIn('older_adult_medication_id', $dueTodayAssignmentIds)
            ->distinct()
            ->pluck('older_adult_medication_id');

        $pendingTodayCount = $dueTodayAssignmentIds
            ->diff($administeredTodayAssignmentIds)
            ->count();
        $pendingUserRequests = User::query()
            ->where('role', '!=', 'admin')
            ->where('is_approved', false)
            ->count();
        $pendingVacationRequests = VacationRequest::query()
            ->where('status', 'pending')
            ->count();
        $pendingScheduleChanges = CaregiverSchedule::query()
            ->where('change_request_status', 'pending')
            ->count();

        return response()->json([
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
                'pending_today' => $pendingTodayCount,
            ],
            'report' => [
                'late_entries' => 0,
                'absences' => 0,
                'vacation_requests' => $pendingVacationRequests,
                'change_requests' => $pendingScheduleChanges,
                'pending_users' => $pendingUserRequests,
            ],
        ]);
    }

    public function medicationStatistics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => 'nullable|in:day,month,year',
        ]);

        $filter = $data['filter'] ?? 'day';
        $timezone = (string) config('app.timezone');
        $today = Carbon::now($timezone)->startOfDay();
        [$startDate, $endDate] = $this->periodRange($filter, $today);

        $administrations = MedicationAdministration::query()
            ->with(['medication:id,name', 'olderAdult:id,full_name'])
            ->whereBetween('administration_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('administration_date')
            ->orderBy('administration_time')
            ->get();

        $items = $administrations
            ->groupBy('medication_id')
            ->map(function ($records) use ($filter, $startDate) {
                $first = $records->first();
                $name = $first?->medication?->name ?? 'Medicamento';
                $patients = $records
                    ->pluck('older_adult_id')
                    ->filter()
                    ->unique()
                    ->count();
                $activeDays = $records
                    ->pluck('administration_date')
                    ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString())
                    ->unique()
                    ->count();

                return [
                    'id' => (string) ($first?->medication_id ?? $name),
                    'name' => $name,
                    'totalUses' => $records->count(),
                    'patients' => $patients,
                    'streak' => $activeDays,
                    'streakLabel' => $activeDays === 1 ? '1 dia con registro' : "{$activeDays} dias con registro",
                    'usageLabel' => $this->usageLabel($records->count(), $filter),
                    'chartTitle' => $this->chartTitle($filter),
                    'rankingNote' => $patients === 1 ? '1 paciente registrado' : "{$patients} pacientes registrados",
                    'chart' => $this->buildChart($records, $filter, $startDate),
                ];
            })
            ->sortByDesc('totalUses')
            ->values()
            ->all();

        return response()->json([
            'filter' => $filter,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'items' => $items,
            'inventory' => $this->medicationInventory(),
        ]);
    }

    private function getSpanishDayName(Carbon $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            default => 'domingo',
        };
    }

    private function periodRange(string $filter, Carbon $today): array
    {
        return match ($filter) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };
    }

    private function usageLabel(int $total, string $filter): string
    {
        $period = match ($filter) {
            'month' => 'este mes',
            'year' => 'este ano',
            default => 'hoy',
        };

        return $total === 1 ? "1 administracion {$period}" : "{$total} administraciones {$period}";
    }

    private function chartTitle(string $filter): string
    {
        return match ($filter) {
            'month' => 'Uso semanal del mes',
            'year' => 'Uso mensual del ano',
            default => 'Uso por hora del dia',
        };
    }

    private function buildChart($records, string $filter, Carbon $periodStart): array
    {
        if ($filter === 'year') {
            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $buckets = array_fill(1, 12, 0);

            foreach ($records as $record) {
                $month = Carbon::parse($record->administration_date)->month;
                $buckets[$month] += 1;
            }

            return collect($labels)
                ->map(fn ($label, $index) => ['label' => $label, 'value' => $buckets[$index + 1]])
                ->all();
        }

        if ($filter === 'month') {
            $buckets = array_fill(1, 5, 0);

            foreach ($records as $record) {
                $day = Carbon::parse($record->administration_date)->day;
                $week = min(5, (int) ceil($day / 7));
                $buckets[$week] += 1;
            }

            return collect($buckets)
                ->map(fn ($value, $week) => ['label' => "Sem {$week}", 'value' => $value])
                ->values()
                ->all();
        }

        $buckets = array_fill(0, 8, 0);

        foreach ($records as $record) {
            $time = Carbon::parse((string) $record->administration_time);
            $bucket = min(7, intdiv($time->hour, 3));
            $buckets[$bucket] += 1;
        }

        return collect($buckets)
            ->map(fn ($value, $bucket) => [
                'label' => str_pad((string) ($bucket * 3), 2, '0', STR_PAD_LEFT) . ':00',
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    private function medicationInventory(): array
    {
        return Medication::query()
            ->with(['olderAdultMedications.olderAdult:id,full_name'])
            ->withCount('administrations')
            ->orderBy('name')
            ->get()
            ->map(function (Medication $medication) {
                $activeAssignments = $medication->olderAdultMedications
                    ->filter(fn (OlderAdultMedication $assignment) => (bool) $assignment->is_active);

                return [
                    'id' => $medication->id,
                    'name' => $medication->name,
                    'is_active' => (bool) $medication->is_active,
                    'assigned_patients' => $activeAssignments
                        ->pluck('older_adult_id')
                        ->filter()
                        ->unique()
                        ->count(),
                    'active_assignments' => $activeAssignments->count(),
                    'administrations_count' => $medication->administrations_count,
                ];
            })
            ->values()
            ->all();
    }
}

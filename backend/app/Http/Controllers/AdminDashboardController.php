<?php

namespace App\Http\Controllers;

use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use Illuminate\Http\JsonResponse;
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

        return response()->json([
            'date' => $today->toDateString(),
            'medications' => [
                'due_today' => $dueTodayAssignmentIds->count(),
                'administered_today' => $administeredTodayAssignmentIds->count(),
                'pending_today' => $pendingTodayCount,
            ],
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
}

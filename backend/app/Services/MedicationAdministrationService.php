<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Support\Carbon;

class MedicationAdministrationService
{
    public function markTaken(
        User $user,
        OlderAdultMedication $assignment,
        array $data,
    ): MedicationAdministration {
        $this->authorize($user);
        $olderAdult = $assignment->olderAdult()->first();

        if (! $olderAdult || (int) $olderAdult->professional_caregiver_id !== (int) $user->id) {
            abort(response()->json([
                'message' => 'No tienes acceso para marcar este medicamento.',
            ], 403));
        }

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);
        $date = $now->toDateString();
        $time = isset($data['administration_time'])
            ? Carbon::createFromFormat('H:i', $data['administration_time'], $timezone)->format('H:i:s')
            : $now->format('H:i:s');

        return MedicationAdministration::firstOrCreate([
            'administration_type' => 'scheduled',
            'older_adult_medication_id' => $assignment->id,
            'administration_date' => $date,
        ], [
            'older_adult_id' => $assignment->older_adult_id,
            'medication_id' => $assignment->medication_id,
            'dosage' => $assignment->dosage,
            'administration_time' => $time,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $user->id,
        ]);
    }

    private function authorize(User $user): void
    {
        if (! $user->hasRole(UserRole::PROFESSIONAL)) {
            abort(response()->json([
                'message' => 'No tienes acceso para marcar medicamentos.',
            ], 403));
        }

        if (! $user->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para marcar medicamentos.',
            ], 403));
        }
    }
}

<?php

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

        $administration = MedicationAdministration::query()
            ->where('administration_type', 'scheduled')
            ->whereDate('administration_date', $date)
            ->where('older_adult_medication_id', $assignment->id)
            ->first();

        $values = [
            'older_adult_id' => $assignment->older_adult_id,
            'medication_id' => $assignment->medication_id,
            'dosage' => $assignment->dosage,
            'administration_date' => $date,
            'administration_time' => $time,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $user->id,
        ];

        if ($administration) {
            $administration->update($values);

            return $administration;
        }

        return MedicationAdministration::create([
            'administration_type' => 'scheduled',
            'older_adult_medication_id' => $assignment->id,
            ...$values,
        ]);
    }

    private function authorize(User $user): void
    {
        $role = Str::of((string) $user->role)->ascii()->lower()->trim()->toString();

        if (! in_array($role, ['profesional', 'cuidador_profesional'], true)) {
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

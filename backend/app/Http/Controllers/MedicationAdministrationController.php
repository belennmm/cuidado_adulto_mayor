<?php

namespace App\Http\Controllers;

use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MedicationAdministrationController extends Controller
{
    public function markTaken(Request $request, OlderAdultMedication $assignment): JsonResponse
    {
        $user = $request->user();
        $this->ensureProfessionalCaregiver($user?->role, (bool) $user?->is_approved);

        $olderAdult = $assignment->olderAdult()->first();
        if (!$olderAdult || (int) $olderAdult->professional_caregiver_id !== (int) $user->id) {
            return response()->json([
                'message' => 'No tienes acceso para marcar este medicamento.',
            ], 403);
        }

        $data = $request->validate([
            'administration_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);
        $date = $now->toDateString();

        $time = isset($data['administration_time'])
            ? Carbon::createFromFormat('H:i', $data['administration_time'], $timezone)->format('H:i:s')
            : $now->format('H:i:s');

        $administration = MedicationAdministration::updateOrCreate(
            [
                'administration_type' => 'scheduled',
                'administration_date' => $date,
                'older_adult_medication_id' => $assignment->id,
            ],
            [
                'older_adult_id' => $assignment->older_adult_id,
                'medication_id' => $assignment->medication_id,
                'dosage' => $assignment->dosage,
                'administration_time' => $time,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ],
        );

        return response()->json([
            'message' => 'Medicamento marcado como tomado.',
            'administration' => [
                'id' => $administration->id,
                'older_adult_medication_id' => $administration->older_adult_medication_id,
                'older_adult_id' => $administration->older_adult_id,
                'medication_id' => $administration->medication_id,
                'administration_date' => $administration->administration_date?->toDateString(),
                'administration_time' => $administration->administration_time,
                'notes' => $administration->notes,
                'recorded_by' => $administration->recorded_by,
            ],
        ]);
    }

    private function ensureProfessionalCaregiver(mixed $role, bool $isApproved): void
    {
        $normalized = $this->normalizeRole($role);

        if (!in_array($normalized, ['profesional', 'cuidador_profesional'], true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para marcar medicamentos.',
            ], 403));
        }

        if (!$isApproved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para marcar medicamentos.',
            ], 403));
        }
    }

    private function normalizeRole(mixed $role): string
    {
        return Str::of((string) $role)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }
}



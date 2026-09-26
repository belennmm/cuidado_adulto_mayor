<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\MedicationAcquisition;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OlderAdultService
{
    public function create(array $data, User $creator): OlderAdult
    {
        $data = $this->normalizeCaregiverAssignments($data);
        $medications = $data['medications'] ?? [];
        unset($data['medications']);

        $data['status'] = $data['status'] ?? 'Estable';
        $data['created_by'] = $creator->id;

        return DB::transaction(function () use ($data, $medications) {
            $olderAdult = OlderAdult::create($data);
            $this->syncMedications($olderAdult, $medications);

            return $this->loadRelations($olderAdult);
        });
    }

    public function update(OlderAdult $olderAdult, array $data): OlderAdult
    {
        $data = $this->normalizeCaregiverAssignments($data);
        $shouldSyncMedications = array_key_exists('medications', $data);
        $medications = $data['medications'] ?? [];
        unset($data['medications']);

        $data['status'] = $data['status'] ?? 'Estable';

        DB::transaction(function () use ($olderAdult, $data, $medications, $shouldSyncMedications) {
            $olderAdult->update($data);

            if ($shouldSyncMedications) {
                $this->syncMedications($olderAdult, $medications);
            }
        });

        return $this->loadRelations($olderAdult->refresh());
    }

    public function loadRelations(OlderAdult $olderAdult): OlderAdult
    {
        return $olderAdult->load([
            'medicationAssignments.medication',
            'familyCaregiver',
            'professionalCaregiver',
        ]);
    }

    private function normalizeCaregiverAssignments(array $data): array
    {
        $familyCaregiverId = $data['family_caregiver_id'] ?? null;
        $familyCaregiver = null;

        if ($familyCaregiverId) {
            $familyCaregiver = User::query()
                ->where('role', 'familiar')
                ->where('is_approved', true)
                ->find($familyCaregiverId);
        } elseif (! empty($data['caregiver_family'])) {
            $familyCaregiver = User::query()
                ->where('role', 'familiar')
                ->where('is_approved', true)
                ->whereRaw('LOWER(name) = ?', [strtolower((string) $data['caregiver_family'])])
                ->first();
        }

        $data['family_caregiver_id'] = $familyCaregiver?->id;
        $data['caregiver_family'] = $familyCaregiver?->name;

        return $data;
    }

    private function syncMedications(OlderAdult $olderAdult, array $medications): void
    {
        $keptAssignmentIds = [];

        foreach ($medications as $medicationData) {
            $name = trim((string) ($medicationData['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $medication = Medication::firstOrCreate(['name' => $name], ['is_active' => true]);
            $assignmentData = [
                'medication_id' => $medication->id,
                'dosage' => $this->nullableString($medicationData['dosage'] ?? null),
                'schedule' => $this->nullableString($medicationData['schedule'] ?? null),
                'days' => $this->normalizeDays($medicationData['days'] ?? []),
                'notes' => $this->nullableString($medicationData['notes'] ?? null),
                'is_active' => true,
            ];

            $assignmentId = $medicationData['id'] ?? null;
            $assignment = $assignmentId
                ? $olderAdult->medicationAssignments()->find($assignmentId)
                : null;

            if ($assignment) {
                $assignment->update($assignmentData);
            } else {
                $assignment = $olderAdult->medicationAssignments()->create([
                    ...$assignmentData,
                    'presentation' => $this->nullableString($medicationData['presentation'] ?? null),
                    'quantity' => (int) ($medicationData['quantity'] ?? 0),
                    'unit' => $this->nullableString($medicationData['unit'] ?? null) ?? 'unidades',
                    'minimum_stock' => (int) ($medicationData['minimum_stock'] ?? 0),
                    'expiration_date' => $medicationData['expiration_date'] ?? null,
                ]);

                if ((int) $assignment->quantity > 0) {
                    MedicationAcquisition::create([
                        'medication_id' => $medication->id,
                        'older_adult_id' => $olderAdult->id,
                        'quantity' => (int) $assignment->quantity,
                        'acquired_at' => now(config('app.timezone')),
                    ]);
                }
            }

            $keptAssignmentIds[] = $assignment->id;
        }

        $olderAdult->medicationAssignments()
            ->when($keptAssignmentIds, fn ($query) => $query->whereNotIn('id', $keptAssignmentIds))
            ->delete();
    }

    private function normalizeDays(mixed $days): array
    {
        if (! is_array($days)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($day) {
            $value = trim((string) $day);

            return $value !== '' ? $value : null;
        }, $days)));
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) ($value ?? ''));

        return $stringValue !== '' ? $stringValue : null;
    }
}

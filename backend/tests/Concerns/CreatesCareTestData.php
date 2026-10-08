<?php

namespace Tests\Concerns;

use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;

trait CreatesCareTestData
{
    protected function createApprovedProfessional(array $attributes = []): User
    {
        return User::factory()->approvedProfessional()->create($attributes);
    }

    protected function createApprovedFamily(array $attributes = []): User
    {
        return User::factory()->approvedFamily()->create($attributes);
    }

    protected function createOlderAdult(array $attributes = []): OlderAdult
    {
        return OlderAdult::factory()->create($attributes);
    }

    protected function createAssignedOlderAdult(User $caregiver, array $attributes = []): OlderAdult
    {
        $factory = OlderAdult::factory();

        if (in_array($caregiver->role, ['familiar', 'cuidador_familiar'], true)) {
            $factory = $factory->assignedToFamily($caregiver);
        } else {
            $factory = $factory->assignedToProfessional($caregiver);
        }

        return $factory->create($attributes);
    }

    protected function createMedicationAssignment(OlderAdult $olderAdult, array $attributes = []): OlderAdultMedication
    {
        return OlderAdultMedication::factory()->create([
            'older_adult_id' => $olderAdult->id,
            ...$attributes,
        ]);
    }
}

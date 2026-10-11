<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class ProfileMedicationIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    private function profileWithAdministration(): array
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Nombre original']);
        $assignment = $this->createMedicationAssignment($adult);
        Sanctum::actingAs($professional);
        $this->postJson("/api/medications/{$assignment->id}/taken")->assertOk();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));

        return [$adult, $assignment];
    }

    public function test_empty_or_null_medications_cannot_remove_recorded_assignments(): void
    {
        [$adult, $assignment] = $this->profileWithAdministration();
        foreach ([[], null] as $medications) {
            $this->putJson("/api/admin/older-adults/{$adult->id}", [
                'full_name' => 'Cambio rechazado', 'medications' => $medications,
            ])->assertUnprocessable();
            $this->assertSame('Nombre original', $adult->fresh()->full_name);
            $this->assertModelExists($assignment);
            $this->assertDatabaseHas('medication_administrations', [
                'older_adult_medication_id' => $assignment->id,
            ]);
        }
    }

    public function test_late_failure_rolls_back_profile_other_assignments_catalog_and_acquisitions(): void
    {
        [$adult, $protected] = $this->profileWithAdministration();
        $kept = $this->createMedicationAssignment($adult, ['dosage' => 'Dosis original']);
        $catalogCount = Medication::query()->count();

        $this->putJson("/api/admin/older-adults/{$adult->id}", [
            'full_name' => 'Cambio rechazado',
            'medications' => [
                ['id' => $kept->id, 'name' => $kept->medication->name, 'dosage' => 'Dosis modificada'],
                ['name' => 'Medicamento nuevo rechazado', 'quantity' => 7],
            ],
        ])->assertUnprocessable();

        $this->assertSame('Nombre original', $adult->fresh()->full_name);
        $this->assertSame('Dosis original', $kept->fresh()->dosage);
        $this->assertSame($catalogCount, Medication::query()->count());
        $this->assertDatabaseCount('medication_acquisitions', 0);
        $this->assertSame(2, $adult->medicationAssignments()->count());
        $this->assertDatabaseHas('medication_administrations', [
            'older_adult_medication_id' => $protected->id,
        ]);
    }

    public function test_keeping_a_recorded_assignment_allows_profile_editing(): void
    {
        [$adult, $assignment] = $this->profileWithAdministration();
        $this->putJson("/api/admin/older-adults/{$adult->id}", [
            'full_name' => 'Nombre actualizado',
            'medications' => [['id' => $assignment->id, 'name' => $assignment->medication->name]],
        ])->assertOk();
        $this->assertSame('Nombre actualizado', $adult->fresh()->full_name);
        $this->assertDatabaseHas('medication_administrations', [
            'older_adult_medication_id' => $assignment->id,
        ]);
    }

    public function test_omitting_the_medications_field_does_not_delete_assignments(): void
    {
        [$adult, $assignment] = $this->profileWithAdministration();
        $this->putJson("/api/admin/older-adults/{$adult->id}", ['full_name' => 'Nombre actualizado'])->assertOk();
        $this->assertModelExists($assignment);
        $this->assertDatabaseCount('medication_administrations', 1);
    }

    public function test_assignments_without_administrations_can_still_be_removed(): void
    {
        [$adult, $protected] = $this->profileWithAdministration();
        $removable = $this->createMedicationAssignment($adult);
        $this->putJson("/api/admin/older-adults/{$adult->id}", [
            'full_name' => $adult->full_name,
            'medications' => [['id' => $protected->id, 'name' => $protected->medication->name]],
        ])->assertOk();
        $this->assertModelMissing($removable);
        $this->assertModelExists($protected);
    }

    public function test_duplicate_assignment_ids_reject_and_roll_back_the_whole_update(): void
    {
        [$adult, $assignment] = $this->profileWithAdministration();
        $entry = ['id' => $assignment->id, 'name' => $assignment->medication->name, 'dosage' => 'Cambio'];
        $this->putJson("/api/admin/older-adults/{$adult->id}", [
            'full_name' => 'Cambio rechazado', 'medications' => [$entry, $entry],
        ])->assertUnprocessable()->assertJsonValidationErrors('medications');
        $this->assertSame('Nombre original', $adult->fresh()->full_name);
        $this->assertSame('1 tableta', $assignment->fresh()->dosage);
        $this->assertSame(1, MedicationAdministration::query()->count());
    }

    public function test_foreign_assignment_id_rejects_the_entire_update(): void
    {
        [$adult, $assignment] = $this->profileWithAdministration();
        $foreign = $this->createMedicationAssignment($this->createOlderAdult());
        $this->putJson("/api/admin/older-adults/{$adult->id}", [
            'full_name' => 'Cambio rechazado',
            'medications' => [['id' => $foreign->id, 'name' => $foreign->medication->name]],
        ])->assertNotFound();
        $this->assertSame('Nombre original', $adult->fresh()->full_name);
        $this->assertModelExists($assignment);
        $this->assertModelExists($foreign);
    }
}

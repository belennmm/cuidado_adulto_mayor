<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class MedicationAdministrationEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_mark_assigned_medication_as_taken(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:15:00'));

        $professional = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Rosa Martinez']);
        $assignment = $this->createMedicationAssignment($olderAdult, [
            'schedule' => '8:00 AM',
        ]);

        Sanctum::actingAs($professional);

        $this->postJson("/api/medications/{$assignment->id}/taken", [
            'administration_time' => '10:00',
            'notes' => 'Administrado sin novedad',
        ])
            ->assertOk()
            ->assertJsonPath('administration.older_adult_medication_id', $assignment->id)
            ->assertJsonPath('administration.administration_date', '2026-05-04')
            ->assertJsonPath('administration.administration_time', '10:00:00');

        $this->assertDatabaseHas('medication_administrations', [
            'older_adult_medication_id' => $assignment->id,
            'administration_type' => 'scheduled',
            'administration_date' => '2026-05-04',
        ]);
    }

    public function test_pending_professional_cannot_mark_taken(): void
    {
        $professional = User::factory()->pendingProfessional()->create();
        $assignment = $this->createMedicationAssignment($this->createAssignedOlderAdult($professional));

        Sanctum::actingAs($professional);

        $this->postJson("/api/medications/{$assignment->id}/taken")
            ->assertForbidden();
    }

    public function test_professional_cannot_mark_other_professionals_assignment(): void
    {
        $owner = $this->createApprovedProfessional();
        $other = $this->createApprovedProfessional();
        $assignment = $this->createMedicationAssignment($this->createAssignedOlderAdult($owner));

        Sanctum::actingAs($other);

        $this->postJson("/api/medications/{$assignment->id}/taken")
            ->assertNotFound();
    }
}

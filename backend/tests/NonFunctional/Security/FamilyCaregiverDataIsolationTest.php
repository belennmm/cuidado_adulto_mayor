<?php

namespace Tests\NonFunctional\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class FamilyCaregiverDataIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    public function test_family_caregiver_can_only_access_assigned_older_adult_data(): void
    {
        $familyCaregiver = $this->createApprovedFamily([
            'name' => 'Laura Rodriguez',
            'email' => 'laura.rodriguez@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'cuidador_familiar',
        ]);

        $admin = User::factory()->admin()->create();

        $assignedOlderAdult = $this->createAssignedOlderAdult($familyCaregiver, [
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'created_by' => $admin->id,
        ]);

        $unassignedOlderAdult = $this->createOlderAdult([
            'full_name' => 'Juan Perez',
            'age' => 75,
            'room' => 'B-202',
            'status' => 'Critico',
            'created_by' => $admin->id,
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $familyCaregiver->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson("/api/family/older-adults/{$assignedOlderAdult->id}")
            ->assertOk()
            ->assertJsonPath('older_adult.id', $assignedOlderAdult->id)
            ->assertJsonPath('older_adult.full_name', 'Rosa Martinez');

        $this->withToken($token)->getJson("/api/family/older-adults/{$unassignedOlderAdult->id}")
            ->assertForbidden();

        $this->withToken($token)->getJson("/api/family/older-adults/{$assignedOlderAdult->id}/incidents")
            ->assertOk()
            ->assertJsonPath('older_adult.id', $assignedOlderAdult->id);

        $this->withToken($token)->getJson("/api/family/older-adults/{$unassignedOlderAdult->id}/incidents")
            ->assertForbidden();

        $this->assertDatabaseHas('older_adults', [
            'id' => $unassignedOlderAdult->id,
            'full_name' => 'Juan Perez',
            'age' => 75,
            'room' => 'B-202',
            'status' => 'Critico',
        ]);
    }

    public function test_family_caregiver_cannot_see_other_family_caregivers_older_adults(): void
    {
        $familyCaregiver1 = $this->createApprovedFamily([
            'name' => 'Laura Rodriguez',
            'password' => Hash::make('secret123'),
        ]);

        $familyCaregiver2 = $this->createApprovedFamily([
            'name' => 'Carlos Lopez',
        ]);

        $admin = User::factory()->admin()->create();

        $olderAdultOfCaregiver1 = $this->createAssignedOlderAdult($familyCaregiver1, [
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'created_by' => $admin->id,
        ]);

        $olderAdultOfCaregiver2 = $this->createAssignedOlderAdult($familyCaregiver2, [
            'full_name' => 'Maria Sanchez',
            'age' => 78,
            'room' => 'A-102',
            'status' => 'Atencion',
            'created_by' => $admin->id,
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $familyCaregiver1->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson("/api/family/older-adults/{$olderAdultOfCaregiver1->id}")
            ->assertOk();

        $this->withToken($token)->getJson("/api/family/older-adults/{$olderAdultOfCaregiver2->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdultOfCaregiver2->id,
            'full_name' => 'Maria Sanchez',
            'family_caregiver_id' => $familyCaregiver2->id,
        ]);
    }
}

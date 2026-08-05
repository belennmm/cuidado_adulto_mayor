<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaregiverAssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_approved_caregivers_and_rejects_nonexistent_or_pending_caregivers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $familyCaregiver = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $professionalCaregiver = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $pendingFamilyCaregiver = User::factory()->create(['role' => 'familiar', 'is_approved' => false]);
        $pendingProfessionalCaregiver = User::factory()->create(['role' => 'profesional', 'is_approved' => false]);

        Sanctum::actingAs($admin);

        $payload = [
            'full_name' => 'Rosa Martínez',
            'age' => 81,
            'status' => 'Estable',
            'family_caregiver_id' => $familyCaregiver->id,
            'professional_caregiver_id' => $professionalCaregiver->id,
        ];

        $response = $this->postJson('/api/admin/older-adults', $payload)
            ->assertCreated()
            ->assertJsonPath('older_adult.family_caregiver_id', $familyCaregiver->id)
            ->assertJsonPath('older_adult.professional_caregiver_id', $professionalCaregiver->id);

        $olderAdultId = $response->json('older_adult.id');

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdultId,
            'family_caregiver_id' => $familyCaregiver->id,
            'professional_caregiver_id' => $professionalCaregiver->id,
        ]);

        $this->putJson("/api/admin/older-adults/{$olderAdultId}", [
            ...$payload,
            'family_caregiver_id' => $pendingFamilyCaregiver->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['family_caregiver_id']);

        $this->putJson("/api/admin/older-adults/{$olderAdultId}", [
            ...$payload,
            'family_caregiver_id' => 999999,
            'professional_caregiver_id' => $pendingProfessionalCaregiver->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['family_caregiver_id', 'professional_caregiver_id']);

        $olderAdult = OlderAdult::findOrFail($olderAdultId);
        $this->assertSame($familyCaregiver->id, $olderAdult->family_caregiver_id);
        $this->assertSame($professionalCaregiver->id, $olderAdult->professional_caregiver_id);
    }
}

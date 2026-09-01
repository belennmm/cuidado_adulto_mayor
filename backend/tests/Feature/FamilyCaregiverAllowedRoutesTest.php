<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FamilyCaregiverAllowedRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_family_caregiver_can_log_in_and_access_every_allowed_read_route(): void
    {
        $familyCaregiver = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'email' => 'laura.rodriguez@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'cuidador_familiar',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => $familyCaregiver->name,
            'family_caregiver_id' => $familyCaregiver->id,
            'created_by' => $familyCaregiver->id,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $familyCaregiver->email,
            'password' => 'secret123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.id', $familyCaregiver->id)
            ->assertJsonPath('user.role', 'cuidador_familiar');

        $token = $loginResponse->json('token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $familyCaregiver->id,
            'tokenable_type' => User::class,
        ]);

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $familyCaregiver->id);

        $this->withToken($token)->getJson('/api/family/overview')->assertOk();
        $this->withToken($token)->getJson('/api/family/older-adults')
            ->assertOk()
            ->assertJsonPath('older_adults.0.id', $olderAdult->id);
        $this->withToken($token)->getJson("/api/family/older-adults/{$olderAdult->id}")
            ->assertOk()
            ->assertJsonPath('older_adult.id', $olderAdult->id);
        $this->withToken($token)->getJson("/api/family/older-adults/{$olderAdult->id}/incidents")
            ->assertOk()
            ->assertJsonPath('older_adult.id', $olderAdult->id);
        $this->withToken($token)->getJson('/api/family/incidents')->assertOk();
        $this->withToken($token)->getJson('/api/family/routine')->assertOk();
        $this->withToken($token)->getJson('/api/family/routines')->assertOk();
        $this->withToken($token)->getJson('/api/incidents')->assertOk();
        $this->withToken($token)->getJson('/api/incidents/today')->assertOk();
    }
}

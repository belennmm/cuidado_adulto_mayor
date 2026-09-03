<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileRoleProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_cannot_change_role_from_profile_update(): void
    {
        $professional = User::factory()->create([
            'name' => 'Mario Lopez',
            'email' => 'mario@example.com',
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->putJson('/api/me', [
            'name' => 'Mario Lopez Actualizado',
            'email' => 'mario.actualizado@example.com',
            'role' => 'admin',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'profesional');

        $this->assertDatabaseHas('users', [
            'id' => $professional->id,
            'name' => 'Mario Lopez Actualizado',
            'email' => 'mario.actualizado@example.com',
            'role' => 'profesional',
        ]);
    }

    public function test_family_caregiver_cannot_change_role_from_profile_update(): void
    {
        $familyCaregiver = User::factory()->create([
            'name' => 'Maria Gonzalez',
            'email' => 'maria@example.com',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($familyCaregiver);

        $this->putJson('/api/me', [
            'name' => 'Maria Gonzalez Actualizada',
            'email' => 'maria.actualizada@example.com',
            'role' => 'admin',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'familiar');

        $this->assertDatabaseHas('users', [
            'id' => $familyCaregiver->id,
            'name' => 'Maria Gonzalez Actualizada',
            'email' => 'maria.actualizada@example.com',
            'role' => 'familiar',
        ]);
    }
}

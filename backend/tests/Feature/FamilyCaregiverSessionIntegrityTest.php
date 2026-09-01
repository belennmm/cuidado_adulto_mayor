<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FamilyCaregiverSessionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_caregiver_session_persists_and_unauthorized_attempts_do_not_modify_data(): void
    {
        $familyCaregiver = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'email' => 'laura.rodriguez@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'cuidador_familiar',
            'is_approved' => true,
        ]);
        $admin = User::factory()->create([
            'name' => 'Administrador Original',
            'email' => 'admin@example.com', 
            'role' => 'admin',
            'is_approved' => true,
        ]);
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);
        $assignedOlderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'caregiver_family' => $familyCaregiver->name,
            'family_caregiver_id' => $familyCaregiver->id,
            'professional_caregiver_id' => $professional->id,
            'created_by' => $admin->id,
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $familyCaregiver->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->json('token');

        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/family/overview')->assertOk();
        $this->withToken($token)->getJson('/api/family/older-adults')->assertOk();
        $this->withToken($token)->getJson("/api/family/older-adults/{$assignedOlderAdult->id}")->assertOk();
        $this->withToken($token)->getJson('/api/family/routines')->assertOk();
        $this->withToken($token)->getJson('/api/incidents')->assertOk();

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $familyCaregiver->id);

        $this->withToken($token)->putJson("/api/admin/users/{$admin->id}", [
            'name' => 'Administrador alterado',
            'email' => 'admin.alterado@example.com',
            'role' => 'admin',
            'is_approved' => true,
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/professional/incidents', [
            'older_adult_id' => $assignedOlderAdult->id,
            'title' => 'Incidente no autorizado',
            'severity' => 'alta',
            'incident_time' => '10:00',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Administrador Original',
            'email' => 'admin@example.com',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'admin.alterado@example.com']);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertSame(0, Incident::query()->count());

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $familyCaregiver->id);
    }
}

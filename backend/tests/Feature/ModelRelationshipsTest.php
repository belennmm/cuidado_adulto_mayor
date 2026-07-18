<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_older_adult_relations_return_creator_caregivers_and_incidents(): void
    {
        $creator = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $family = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $adult = OlderAdult::create([
            'full_name' => 'Paciente de prueba', 'age' => 80, 'gender' => 'Femenino',
            'created_by' => $creator->id, 'family_caregiver_id' => $family->id,
            'professional_caregiver_id' => $professional->id,
        ]);
        Incident::create([
            'title' => 'Caida', 'description' => 'Incidente de prueba', 'adult_name' => $adult->full_name,
            'older_adult_id' => $adult->id, 'severity' => 'media', 'status' => 'pendiente',
            'incident_date' => now()->toDateString(), 'reported_by' => $professional->id,
        ]);

        $this->assertTrue($adult->creator->is($creator));
        $this->assertTrue($adult->familyCaregiver->is($family));
        $this->assertTrue($adult->professionalCaregiver->is($professional));
        $this->assertCount(1, $adult->incidents);
        $this->assertTrue($adult->incidents->first()->reporter->is($professional));
    }

    public function test_user_factory_creates_a_pending_family_user_with_hashed_password(): void
    {
        $user = User::factory()->create();

        $this->assertSame('familiar', $user->role);
        $this->assertFalse($user->is_approved);
        $this->assertTrue(password_verify('password', $user->password));
    }
}

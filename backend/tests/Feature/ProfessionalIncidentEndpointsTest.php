<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalIncidentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_register_incident_for_assigned_older_adult(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-11 10:15:00'));

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Caída leve',
            'severity' => 'alta',
            'incident_time' => '10:00',
        ])
            ->assertCreated()
            ->assertJsonPath('incident.older_adult_id', $olderAdult->id)
            ->assertJsonPath('incident.title', 'Caída leve')
            ->assertJsonPath('incident.severity', 'alta')
            ->assertJsonPath('incident.status', 'abierto')
            ->assertJsonPath('incident.incident_date', '2026-05-11')
            ->assertJsonPath('incident.incident_time', '10:00:00');

        $this->assertDatabaseHas('incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Caída leve',
        ]);
    }

    public function test_unapproved_professional_cannot_register_incidents(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => 1,
            'title' => 'Caída leve',
        ])->assertForbidden();
    }

    public function test_professional_cannot_register_incident_for_other_professionals_older_adult(): void
    {
        $owner = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $other = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($other);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Caída leve',
        ])->assertForbidden();

        $this->assertSame(0, Incident::query()->count());
    }

    public function test_incident_rejects_invalid_severity(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Caída leve',
            'severity' => 'critica',
        ])->assertUnprocessable();
    }
}

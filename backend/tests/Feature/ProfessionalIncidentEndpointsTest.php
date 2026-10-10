<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class ProfessionalIncidentEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_register_incident_for_assigned_older_adult(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-11 10:15:00'));

        $professional = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Rosa Martinez']);

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
        $professional = User::factory()->pendingProfessional()->create();

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => 1,
            'title' => 'Caída leve',
        ])->assertForbidden();
    }

    public function test_professional_cannot_register_incident_for_other_professionals_older_adult(): void
    {
        $owner = $this->createApprovedProfessional();
        $other = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($owner, ['full_name' => 'Rosa Martinez']);

        Sanctum::actingAs($other);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Caída leve',
        ])->assertNotFound();

        $this->assertSame(0, Incident::query()->count());
    }

    public function test_incident_rejects_invalid_severity(): void
    {
        $professional = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Rosa Martinez']);

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Caída leve',
            'severity' => 'critica',
        ])->assertUnprocessable();
    }

    public function test_professional_can_update_incident_notes(): void
    {
        $professional = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Rosa Martinez']);

        $incident = Incident::create([
            'title' => 'Caída leve',
            'older_adult_id' => $olderAdult->id,
            'adult_name' => $olderAdult->full_name,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => '2026-05-11',
            'incident_time' => '10:00:00',
            'reported_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->patchJson("/api/professional/incidents/{$incident->id}", [
            'description' => 'Se aplicó hielo y se monitorea.',
        ])
            ->assertOk()
            ->assertJsonPath('incident.description', 'Se aplicó hielo y se monitorea.');
    }

    public function test_professional_cannot_update_other_professionals_incident(): void
    {
        $owner = $this->createApprovedProfessional();
        $other = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($owner, ['full_name' => 'Rosa Martinez']);

        $incident = Incident::create([
            'title' => 'Caída leve',
            'older_adult_id' => $olderAdult->id,
            'adult_name' => $olderAdult->full_name,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => '2026-05-11',
            'incident_time' => '10:00:00',
            'reported_by' => $owner->id,
        ]);

        Sanctum::actingAs($other);

        $this->patchJson("/api/professional/incidents/{$incident->id}", [
            'description' => 'Intento de modificar.',
        ])->assertNotFound();
    }
}

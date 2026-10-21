<?php

namespace Tests\NonFunctional\Reliability;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

/** SCRUM-1015: consulta de referencia anterior a la restauración. */
class BackupIncidentBaselineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    public function test_admin_can_consult_incidents_from_both_caregivers_by_explicit_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $first = $this->createApprovedProfessional();
        $second = $this->createApprovedProfessional();
        $firstIncident = $this->incidentFor($first);
        $secondIncident = $this->incidentFor($second);
        $this->incidentFor($first, '2026-10-19');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/incidents?date=2026-10-20')
            ->assertOk()
            ->assertJsonCount(2, 'incidents');

        $this->assertEqualsCanonicalizing(
            [$firstIncident->id, $secondIncident->id],
            array_column($response->json('incidents'), 'id'),
        );
        $this->assertDatabaseCount('incidents', 3);
    }

    public function test_professional_can_only_consult_incidents_of_assigned_adults(): void
    {
        $professional = $this->createApprovedProfessional();
        $ownIncident = $this->incidentFor($professional);
        $this->incidentFor($this->createApprovedProfessional());

        Sanctum::actingAs($professional);

        $this->getJson('/api/incidents?date=2026-10-20')
            ->assertOk()
            ->assertJsonCount(1, 'incidents')
            ->assertJsonPath('incidents.0.id', $ownIncident->id);
    }

    public function test_incident_consultation_requires_authentication(): void
    {
        $this->incidentFor($this->createApprovedProfessional());

        $this->getJson('/api/incidents?date=2026-10-20')->assertUnauthorized();
    }

    private function incidentFor(User $professional, string $date = '2026-10-20'): Incident
    {
        $adult = $this->createAssignedOlderAdult($professional);

        return Incident::create([
            'older_adult_id' => $adult->id,
            'adult_name' => $adult->full_name,
            'reported_by' => $professional->id,
            'title' => 'Incidente sintético de recuperación',
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => $date,
            'incident_time' => '10:00:00',
        ]);
    }
}

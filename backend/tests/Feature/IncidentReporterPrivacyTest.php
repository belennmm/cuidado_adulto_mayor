<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IncidentReporterPrivacyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('caregiverAndReporterRoles')]
    public function test_shared_incident_routes_do_not_disclose_reporter_email(string $role, string $reporterRole): void
    {
        [$caregiver, $adult, $reporter, $incident] = $this->incidentFor($role, $reporterRole);
        Sanctum::actingAs($caregiver);

        foreach (['/api/incidents', '/api/incidents/today'] as $path) {
            $this->getJson($path)->assertOk()
                ->assertJsonCount(1, 'incidents')
                ->assertJsonPath('incidents.0.id', $incident->id)
                ->assertJsonPath('incidents.0.older_adult_id', $adult->id)
                ->assertJsonPath('incidents.0.reported_by', $reporter->name)
                ->assertJsonPath('incidents.0.reporter.name', $reporter->name)
                ->assertJsonMissingPath('incidents.0.reporter.email')
                ->assertDontSee($reporter->email);
        }
    }

    public static function caregiverAndReporterRoles(): array
    {
        return [
            ['familiar', 'admin'], ['familiar', 'profesional'],
            ['profesional', 'admin'], ['profesional', 'profesional'],
        ];
    }

    public function test_all_family_incident_representations_hide_reporter_email(): void
    {
        [$family, $adult, $reporter, $incident] = $this->incidentFor('familiar');
        Sanctum::actingAs($family);
        $paths = [
            '/api/family/overview' => ['incidents.0'],
            '/api/family/incidents' => ['incidents.0'],
            '/api/family/older-adults/'.$adult->id.'/incidents' => ['incidents.0'],
            '/api/family/older-adults/'.$adult->id => [
                'older_adult.incidents.0', 'older_adult.last_incident',
            ],
        ];

        foreach ($paths as $path => $entries) {
            $response = $this->getJson($path)->assertOk()->assertDontSee($reporter->email);
            foreach ($entries as $entry) {
                $response->assertJsonPath($entry.'.id', $incident->id)
                    ->assertJsonPath($entry.'.reporter.name', $reporter->name)
                    ->assertJsonMissingPath($entry.'.reporter.email');
            }
        }
    }

    public function test_administrative_incident_access_preserves_reporter_contact(): void
    {
        [, , $admin, $incident] = $this->incidentFor('familiar');
        Sanctum::actingAs($admin);

        foreach (['/api/incidents', '/api/incidents/today'] as $path) {
            $this->getJson($path)->assertOk()
                ->assertJsonPath('incidents.0.id', $incident->id)
                ->assertJsonPath('incidents.0.reporter.email', $admin->email);
        }
    }

    private function incidentFor(string $role, string $reporterRole = 'admin'): array
    {
        $caregiver = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $reporter = User::factory()->create([
            'role' => $reporterRole, 'is_approved' => true, 'email' => 'private-reporter@example.com',
        ]);
        $adult = OlderAdult::create([
            'full_name' => 'Adulto asignado', 'status' => 'Estable', 'created_by' => $reporter->id,
            ($role === 'familiar' ? 'family_caregiver_id' : 'professional_caregiver_id') => $caregiver->id,
        ]);
        $incident = Incident::create([
            'title' => 'Incidente autorizado', 'older_adult_id' => $adult->id,
            'adult_name' => $adult->full_name, 'reported_by' => $reporter->id,
            'incident_date' => now(config('app.timezone'))->toDateString(),
            'severity' => 'media', 'status' => 'abierto',
        ]);

        return [$caregiver, $adult, $reporter, $incident];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_incidents_endpoint_defaults_to_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 09:00:00'));

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

        Incident::create([
            'title' => 'Incidente hoy',
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        Incident::create([
            'title' => 'Incidente ayer',
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'alta',
            'status' => 'abierto',
            'incident_date' => Carbon::yesterday()->toDateString(),
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/incidents')
            ->assertOk()
            ->assertJsonPath('date', Carbon::today()->toDateString())
            ->assertJsonCount(1, 'incidents')
            ->assertJsonPath('incidents.0.title', 'Incidente hoy');
    }

    public function test_incidents_endpoint_filters_by_requested_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 09:00:00'));

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

        Incident::create([
            'title' => 'Incidente 2026-04-29',
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => '2026-04-29',
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        Incident::create([
            'title' => 'Incidente 2026-04-30',
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'alta',
            'status' => 'abierto',
            'incident_date' => '2026-04-30',
            'incident_time' => '09:30:00',
            'reported_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/incidents?date=2026-04-29')
            ->assertOk()
            ->assertJsonPath('date', '2026-04-29')
            ->assertJsonCount(1, 'incidents')
            ->assertJsonPath('incidents.0.title', 'Incidente 2026-04-29');
    }

    public function test_incidents_endpoint_validates_date_format(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/incidents?date=2026-4-1')
            ->assertUnprocessable();
    }
}


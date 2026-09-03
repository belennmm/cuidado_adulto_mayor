<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminIncidentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_register_incident_for_any_older_adult(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-24 20:50:00'));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Control de presión',
            'description' => 'Se registró presión elevada y se notificó al equipo.',
            'severity' => 'alta',
            'incident_date' => '2026-05-24',
            'incident_time' => '20:50',
        ])
            ->assertCreated()
            ->assertJsonPath('incident.older_adult_id', $olderAdult->id)
            ->assertJsonPath('incident.title', 'Control de presión')
            ->assertJsonPath('incident.severity', 'alta')
            ->assertJsonPath('incident.status', 'abierto')
            ->assertJsonPath('incident.incident_date', '2026-05-24')
            ->assertJsonPath('incident.incident_time', '20:50:00');

        $this->assertDatabaseHas('incidents', [
            'older_adult_id' => $olderAdult->id,
            'title' => 'Control de presión',
            'reported_by' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_use_admin_incident_endpoint(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/admin/incidents', [
            'older_adult_id' => 1,
            'title' => 'Intento no permitido',
        ])->assertForbidden();

        $this->assertSame(0, Incident::query()->count());
    }
}

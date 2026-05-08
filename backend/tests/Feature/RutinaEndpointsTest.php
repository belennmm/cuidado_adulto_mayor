<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RutinaEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_rutinas_returns_only_assigned_professional_routines(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $otherProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $otherAdult = OlderAdult::create([
            'full_name' => 'Jose Lopez',
            'status' => 'Estable',
            'professional_caregiver_id' => $otherProfessional->id,
            'created_by' => $otherProfessional->id,
        ]);

        $assignedAdult->rutinas()->create([
            'created_by' => $professional->id,
            'nombre' => 'Rutina matutina',
            'horario' => '08:00',
            'actividades' => ['Tomar signos vitales'],
        ]);

        $otherAdult->rutinas()->create([
            'created_by' => $otherProfessional->id,
            'nombre' => 'Rutina externa',
            'horario' => '09:00',
            'actividades' => ['Revision general'],
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/rutinas')
            ->assertOk()
            ->assertJsonCount(1, 'rutinas')
            ->assertJsonPath('rutinas.0.nombre', 'Rutina matutina')
            ->assertJsonPath('rutinas.0.adulto_mayor_id', $assignedAdult->id);
    }

    public function test_post_rutinas_creates_a_routine_for_assigned_professional(): void
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

        $this->postJson('/api/rutinas', [
            'nombre' => 'Rutina matutina',
            'horario' => '08:00',
            'actividades' => ['Tomar signos vitales', 'Desayuno asistido'],
            'adulto_mayor_id' => $olderAdult->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Rutina creada correctamente.')
            ->assertJsonPath('rutina.nombre', 'Rutina matutina')
            ->assertJsonPath('rutina.horario', '08:00')
            ->assertJsonPath('rutina.adulto_mayor_id', $olderAdult->id)
            ->assertJsonPath('rutina.actividades.0', 'Tomar signos vitales')
            ->assertJsonPath('rutina.adulto_mayor.full_name', 'Rosa Martinez');

        $this->assertDatabaseHas('rutinas', [
            'nombre' => 'Rutina matutina',
            'horario' => '08:00',
            'older_adult_id' => $olderAdult->id,
            'created_by' => $professional->id,
        ]);

        $this->assertTrue($olderAdult->rutinas()->where('nombre', 'Rutina matutina')->exists());
    }

    public function test_post_rutinas_requires_valid_schedule_format(): void
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

        $this->postJson('/api/rutinas', [
            'nombre' => 'Rutina matutina',
            'horario' => '25:80',
            'actividades' => ['Tomar signos vitales'],
            'adulto_mayor_id' => $olderAdult->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['horario']);
    }

    public function test_post_rutinas_requires_an_existing_older_adult(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/rutinas', [
            'nombre' => 'Rutina matutina',
            'horario' => '08:00',
            'actividades' => ['Tomar signos vitales'],
            'adulto_mayor_id' => 999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['adulto_mayor_id']);
    }

    public function test_post_rutinas_rejects_unassigned_older_adult(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $otherProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Jose Lopez',
            'status' => 'Estable',
            'professional_caregiver_id' => $otherProfessional->id,
            'created_by' => $otherProfessional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/rutinas', [
            'nombre' => 'Rutina nocturna',
            'horario' => '20:00',
            'actividades' => ['Cena', 'Preparacion para dormir'],
            'adulto_mayor_id' => $olderAdult->id,
        ])->assertForbidden();
    }

    public function test_post_rutinas_validates_required_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/rutinas', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'horario', 'actividades', 'adulto_mayor_id']);
    }
}

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

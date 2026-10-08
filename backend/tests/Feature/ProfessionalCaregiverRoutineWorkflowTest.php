<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\Rutina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfessionalCaregiverRoutineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_approved_professional_caregiver_can_create_update_and_complete_an_assigned_routine(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 10:30:00'));

        $professional = User::factory()->create([
            'email' => 'profesional@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'cuidador_profesional',
            'is_approved' => true,
        ]);
        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $professional->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->assertNotEmpty($token);

        $routineId = $this->withToken($token)->postJson('/api/rutinas', [
            'nombre' => 'Rutina de manana',
            'horario' => '08:00',
            'actividades' => ['Tomar signos vitales'],
            'adulto_mayor_id' => $olderAdult->id,
        ])
            ->assertCreated()
            ->assertJsonPath('rutina.created_by', $professional->id)
            ->assertJsonPath('rutina.adulto_mayor_id', $olderAdult->id)
            ->json('rutina.id');

        $this->withToken($token)->putJson("/api/rutinas/{$routineId}", [
            'nombre' => 'Rutina actualizada',
            'horario' => '17:30',
            'actividades' => ['Merienda asistida', 'Caminata breve'],
        ])
            ->assertOk()
            ->assertJsonPath('rutina.nombre', 'Rutina actualizada')
            ->assertJsonPath('rutina.horario', '17:30')
            ->assertJsonPath('rutina.actividades.1', 'Caminata breve')
            ->assertJsonPath('rutina.completada', false);

        $this->withToken($token)->patchJson("/api/rutinas/{$routineId}/completar", [
            'actividad_index' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('rutina.actividades_completadas.0.completada', true)
            ->assertJsonPath('rutina.completada', false);

        $this->withToken($token)->patchJson("/api/rutinas/{$routineId}/completar", [
            'actividad_index' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('rutina.actividades_completadas.1.completada', true)
            ->assertJsonPath('rutina.completada', true);

        $this->assertDatabaseHas('rutinas', [
            'id' => $routineId,
            'older_adult_id' => $olderAdult->id,
            'created_by' => $professional->id,
            'nombre' => 'Rutina actualizada',
            'horario' => '17:30',
            'completada' => true,
        ]);

        $routine = Rutina::findOrFail($routineId);

        $this->assertSame(['Merienda asistida', 'Caminata breve'], $routine->actividades);
        $this->assertTrue($routine->actividades_completadas[0]['completada']);
        $this->assertTrue($routine->actividades_completadas[1]['completada']);
        $this->assertSame('2026-09-25 10:30:00', $routine->completada_at->format('Y-m-d H:i:s'));
    }
}

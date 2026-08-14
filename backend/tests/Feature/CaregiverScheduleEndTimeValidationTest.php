<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaregiverScheduleEndTimeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_cannot_be_created_with_end_time_before_start_time(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $payload = [
            'day_of_week' => 3,
            'start_time' => '16:00',
            'end_time' => '08:00',
            'notes' => 'Horario invalido para prueba de creacion',
        ];

        $this->assertDatabaseCount('caregiver_schedules', 0);

        $this->postJson('/api/schedules', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'El horario es inválido.')
            ->assertJsonValidationErrors('end_time');
    }

    public function test_schedule_cannot_be_updated_with_end_time_before_start_time(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 4,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'notes' => 'Horario original',
        ]);

        Sanctum::actingAs($professional);

        $payload = [
            'day_of_week' => 4,
            'start_time' => '18:00',
            'end_time' => '10:00',
            'notes' => 'Horario invalido para prueba de actualizacion',
        ];

        $this->assertDatabaseHas('caregiver_schedules', [
            'id' => $schedule->id,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'notes' => 'Horario original',
        ]);

        $this->putJson("/api/schedules/{$schedule->id}", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'El horario es inválido.')
            ->assertJsonValidationErrors('end_time');
    }
}

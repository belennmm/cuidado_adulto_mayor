<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaregiverScheduleUnauthorizedModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_cannot_modify_another_professionals_schedule(): void
    {
        $owner = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $unauthorizedProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $schedule = CaregiverSchedule::create([
            'user_id' => $owner->id,
            'day_of_week' => 2,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'notes' => 'Horario original del profesional',
        ]);

        $this->assertDatabaseHas('caregiver_schedules', [
            'id' => $schedule->id,
            'user_id' => $owner->id,
            'day_of_week' => 2,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'notes' => 'Horario original del profesional',
        ]);

        Sanctum::actingAs($unauthorizedProfessional);

        $updateResponse = $this->putJson("/api/schedules/{$schedule->id}", [
            'day_of_week' => 5,
            'start_time' => '10:00',
            'end_time' => '18:00',
            'notes' => 'Modificacion no autorizada',
        ]);

        $deleteResponse = $this->deleteJson("/api/admin/schedules/{$schedule->id}");

        $updateResponse
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para modificar este horario.');

        $deleteResponse
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede realizar esta accion.');

        $this->assertDatabaseHas('caregiver_schedules', [
            'id' => $schedule->id,
            'user_id' => $owner->id,
            'day_of_week' => 2,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'notes' => 'Horario original del profesional',
        ]);

        $this->assertDatabaseMissing('caregiver_schedules', [
            'id' => $schedule->id,
            'day_of_week' => 5,
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'notes' => 'Modificacion no autorizada',
        ]);
    }
}

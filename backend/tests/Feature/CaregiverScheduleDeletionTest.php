<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaregiverScheduleDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_an_existing_schedule(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'notes' => 'Horario para prueba de eliminacion',
        ]);

        $this->assertDatabaseHas('caregiver_schedules', [
            'id' => $schedule->id,
            'user_id' => $professional->id,
            'day_of_week' => 1,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/schedules/{$schedule->id}")
            ->assertOk()
            ->assertExactJson([
                'message' => 'Turno eliminado correctamente.',
            ]);

        $this->assertDatabaseMissing('caregiver_schedules', [
            'id' => $schedule->id,
        ]);
    }
}

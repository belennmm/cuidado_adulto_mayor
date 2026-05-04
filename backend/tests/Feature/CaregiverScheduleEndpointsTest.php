<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaregiverScheduleEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_create_schedule_for_day(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $response = $this->postJson('/api/schedules', [
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'notes' => 'Turno mañana',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('schedule.user_id', $professional->id)
            ->assertJsonPath('schedule.day_of_week', 1)
            ->assertJsonPath('schedule.start_time', '08:00:00')
            ->assertJsonPath('schedule.end_time', '16:00:00');

        $this->assertDatabaseHas('caregiver_schedules', [
            'user_id' => $professional->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_schedule_requires_valid_time_format(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/schedules', [
            'day_of_week' => 2,
            'start_time' => '8:00',
            'end_time' => '16:00',
        ])->assertUnprocessable();
    }

    public function test_schedule_requires_end_time_after_start_time(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/schedules', [
            'day_of_week' => 3,
            'start_time' => '16:00',
            'end_time' => '08:00',
        ])->assertUnprocessable();
    }

    public function test_pending_professional_cannot_manage_schedules(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/schedules', [
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertForbidden();
    }

    public function test_non_professional_user_cannot_manage_schedules(): void
    {
        $family = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($family);

        $this->postJson('/api/schedules', [
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertForbidden();
    }

    public function test_professional_can_update_own_schedule(): void
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
        ]);

        Sanctum::actingAs($professional);

        $this->putJson("/api/schedules/{$schedule->id}", [
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'notes' => 'Actualizado',
        ])
            ->assertOk()
            ->assertJsonPath('schedule.start_time', '09:00:00')
            ->assertJsonPath('schedule.end_time', '17:00:00');
    }

    public function test_professional_cannot_update_other_users_schedule(): void
    {
        $owner = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $other = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $schedule = CaregiverSchedule::create([
            'user_id' => $owner->id,
            'day_of_week' => 5,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        Sanctum::actingAs($other);

        $this->putJson("/api/schedules/{$schedule->id}", [
            'day_of_week' => 5,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])->assertForbidden();
    }

    public function test_admin_can_assign_schedule_to_professional(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/schedules', [
            'user_id' => $professional->id,
            'day_of_week' => 1,
            'start_time' => '07:00',
            'end_time' => '15:00',
            'notes' => 'Turno asignado por administracion',
        ])
            ->assertCreated()
            ->assertJsonPath('schedule.user_id', $professional->id)
            ->assertJsonPath('schedule.user.id', $professional->id)
            ->assertJsonPath('schedule.start_time', '07:00:00')
            ->assertJsonPath('schedule.end_time', '15:00:00');

        $this->assertDatabaseHas('caregiver_schedules', [
            'user_id' => $professional->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_admin_can_list_assigned_schedules(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 2,
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/schedules')
            ->assertOk()
            ->assertJsonPath('schedules.0.user_id', $professional->id)
            ->assertJsonPath('schedules.0.user.name', $professional->name);
    }

    public function test_admin_cannot_assign_schedule_to_pending_professional(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/schedules', [
            'user_id' => $professional->id,
            'day_of_week' => 1,
            'start_time' => '07:00',
            'end_time' => '15:00',
        ])->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\OlderAdult;
use App\Models\Rutina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileRelationshipPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_preserves_existing_older_adult_schedule_and_routine_relationships(): void
    {
        $professional = User::factory()->create([
            'name' => 'Mario Lopez',
            'email' => 'mario@example.com',
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'notes' => 'Turno diurno',
        ]);

        $routine = Rutina::create([
            'older_adult_id' => $olderAdult->id,
            'created_by' => $professional->id,
            'nombre' => 'Rutina de mañana',
            'horario' => '09:00',
            'actividades' => ['Tomar agua', 'Caminar'],
        ]);

        Sanctum::actingAs($professional);

        $this->putJson('/api/me', [
            'name' => 'Mario Lopez Actualizado',
            'email' => 'mario.actualizado@example.com',
            'phone' => '5555-0101',
            'location' => 'Zona 10',
            'birthdate' => '1990-05-24',
        ])->assertOk();

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $this->assertDatabaseHas('caregiver_schedules', [
            'id' => $schedule->id,
            'user_id' => $professional->id,
        ]);

        $this->assertDatabaseHas('rutinas', [
            'id' => $routine->id,
            'older_adult_id' => $olderAdult->id,
            'created_by' => $professional->id,
        ]);
    }

    public function test_profile_update_only_changes_allowed_profile_fields(): void
    {
        $professional = User::factory()->create([
            'name' => 'Mario Lopez',
            'email' => 'mario@example.com',
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

        $this->putJson('/api/me', [
            'name' => 'Mario Lopez Actualizado',
            'email' => 'mario.actualizado@example.com',
            'phone' => '5555-0101',
            'location' => 'Zona 10',
            'birthdate' => '1990-05-24',
            'role' => 'admin',
            'is_approved' => false,
            'professional_caregiver_id' => null,
            'created_by' => null,
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Mario Lopez Actualizado')
            ->assertJsonPath('user.email', 'mario.actualizado@example.com')
            ->assertJsonPath('user.role', 'profesional')
            ->assertJsonPath('user.is_approved', true);

        $this->assertDatabaseHas('users', [
            'id' => $professional->id,
            'name' => 'Mario Lopez Actualizado',
            'email' => 'mario.actualizado@example.com',
            'phone' => '5555-0101',
            'location' => 'Zona 10',
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);
    }
}

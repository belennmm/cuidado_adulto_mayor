<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\MobilityExercise;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\RoutineNote;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FamilyCaregiverUnauthorizedRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_caregiver_cannot_access_or_modify_administrator_routes(): void
    {
        $familyCaregiver = $this->approvedFamilyCaregiver();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'created_by' => $admin->id,
        ]);
        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);
        $vacationRequest = VacationRequest::create([
            'user_id' => $professional->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'reason' => 'Solicitud original',
            'status' => 'pending',
        ]);
        $medication = Medication::create(['name' => 'Losartan', 'is_active' => true]);
        $inventoryItem = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00 AM',
            'is_active' => true,
        ]);
        $exercise = MobilityExercise::create([
            'slug' => 'movilidad-suave',
            'title' => 'Movilidad suave',
            'focus' => 'Piernas',
            'duration_minutes' => 10,
            'repetitions' => '10 repeticiones',
            'instructions' => ['Sentarse con apoyo.'],
            'precaution' => 'Detener ante dolor.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        Sanctum::actingAs($familyCaregiver);

        $requests = [
            fn () => $this->getJson('/api/admin/dashboard-summary'),
            fn () => $this->getJson('/api/admin/users'),
            fn () => $this->postJson('/api/admin/users', []),
            fn () => $this->getJson('/api/admin/professional-caregivers'),
            fn () => $this->getJson('/api/admin/family-caregivers'),
            fn () => $this->getJson("/api/admin/users/{$admin->id}"),
            fn () => $this->putJson("/api/admin/users/{$admin->id}", []),
            fn () => $this->patchJson("/api/admin/users/{$admin->id}/approve"),
            fn () => $this->deleteJson("/api/admin/users/{$admin->id}/reject"),
            fn () => $this->deleteJson("/api/admin/users/{$admin->id}"),
            fn () => $this->getJson('/api/admin/schedules'),
            fn () => $this->getJson('/api/admin/schedules/calendar'),
            fn () => $this->postJson('/api/admin/schedules', []),
            fn () => $this->patchJson("/api/admin/schedules/{$schedule->id}/change-request/approve"),
            fn () => $this->patchJson("/api/admin/schedules/{$schedule->id}/change-request/reject"),
            fn () => $this->deleteJson("/api/admin/schedules/{$schedule->id}"),
            fn () => $this->getJson('/api/admin/vacation-requests'),
            fn () => $this->patchJson("/api/admin/vacation-requests/{$vacationRequest->id}/approve"),
            fn () => $this->patchJson("/api/admin/vacation-requests/{$vacationRequest->id}/reject"),
            fn () => $this->getJson('/api/admin/medication-statistics'),
            fn () => $this->getJson('/api/admin/medications/inventory'),
            fn () => $this->postJson('/api/admin/medications/inventory', []),
            fn () => $this->putJson("/api/admin/medications/inventory/{$inventoryItem->id}", []),
            fn () => $this->patchJson("/api/admin/medications/inventory/{$inventoryItem->id}/stock", []),
            fn () => $this->deleteJson("/api/admin/medications/inventory/{$inventoryItem->id}"),
            fn () => $this->getJson('/api/admin/older-adults'),
            fn () => $this->postJson('/api/admin/older-adults', []),
            fn () => $this->getJson("/api/admin/older-adults/{$olderAdult->id}"),
            fn () => $this->putJson("/api/admin/older-adults/{$olderAdult->id}", []),
            fn () => $this->deleteJson("/api/admin/older-adults/{$olderAdult->id}"),
            fn () => $this->getJson('/api/admin/mobility-exercises'),
            fn () => $this->postJson('/api/admin/mobility-exercises', []),
            fn () => $this->getJson("/api/admin/mobility-exercises/{$exercise->id}"),
            fn () => $this->putJson("/api/admin/mobility-exercises/{$exercise->id}", []),
            fn () => $this->deleteJson("/api/admin/mobility-exercises/{$exercise->id}"),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('older_adults', 1);
        $this->assertDatabaseCount('caregiver_schedules', 1);
        $this->assertDatabaseCount('vacation_requests', 1);
        $this->assertDatabaseCount('medications', 1);
        $this->assertDatabaseCount('mobility_exercises', 1);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseHas('vacation_requests', ['id' => $vacationRequest->id, 'status' => 'pending']);
    }

    public function test_family_caregiver_cannot_access_or_modify_professional_routes(): void
    {
        $familyCaregiver = $this->approvedFamilyCaregiver();
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);
        $incident = Incident::create([
            'title' => 'Incidente original',
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => '2026-08-31',
            'reported_by' => $professional->id,
        ]);
        $routineNote = RoutineNote::create([
            'older_adult_id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Nota original',
            'note_date' => '2026-08-31',
        ]);

        Sanctum::actingAs($familyCaregiver);

        $requests = [
            fn () => $this->getJson('/api/professional/overview'),
            fn () => $this->getJson('/api/professional/older-adults'),
            fn () => $this->getJson("/api/professional/older-adults/{$olderAdult->id}"),
            fn () => $this->postJson('/api/professional/incidents', []),
            fn () => $this->patchJson("/api/professional/incidents/{$incident->id}", []),
            fn () => $this->getJson('/api/professional/routines'),
            fn () => $this->getJson('/api/professional/reminders'),
            fn () => $this->getJson('/api/professional/routine-notes'),
            fn () => $this->postJson('/api/professional/routine-notes', []),
            fn () => $this->getJson("/api/professional/routine-notes/{$routineNote->id}"),
            fn () => $this->putJson("/api/professional/routine-notes/{$routineNote->id}", []),
            fn () => $this->deleteJson("/api/professional/routine-notes/{$routineNote->id}"),
            fn () => $this->getJson('/api/professional/schedules'),
            fn () => $this->getJson('/api/professional/vacation-requests'),
            fn () => $this->postJson('/api/professional/vacation-requests', []),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('routine_notes', 1);
        $this->assertDatabaseCount('vacation_requests', 0);
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Incidente original',
            'status' => 'abierto',
        ]);
        $this->assertDatabaseHas('routine_notes', [
            'id' => $routineNote->id,
            'content' => 'Nota original',
            'professional_caregiver_id' => $professional->id,
        ]);
    }

    private function approvedFamilyCaregiver(): User
    {
        return User::factory()->create([
            'role' => 'cuidador_familiar',
            'is_approved' => true,
        ]);
    }
}

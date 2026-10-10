<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\RoutineNote;
use App\Models\Rutina;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IdorAndPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('caregiverRoles')]
    public function test_foreign_and_missing_resources_are_indistinguishable_for_caregivers(string $role, string $module): void
    {
        $actor = $this->user($role);
        $foreign = $this->resources();
        $headers = $this->headersFor($actor);
        $routineData = ['nombre' => 'Cambio indebido', 'horario' => '10:00', 'actividades' => ['Caminar']];
        $cases = [
            ['GET', "/api/{$module}/older-adults/{id}", [], $foreign['adult']->id, null],
            ['GET', "/api/{$module}/routines?older_adult_id={id}", [], $foreign['adult']->id, null],
            ['GET', '/api/rutinas?older_adult_id={id}', [], $foreign['adult']->id, null],
            ['POST', '/api/rutinas', $routineData, $foreign['adult']->id, 'older_adult_id'],
            ['PUT', '/api/rutinas/{id}', $routineData, $foreign['routine']->id, null],
            ['PATCH', '/api/rutinas/{id}/completar', ['actividad_index' => 0], $foreign['routine']->id, null],
            ['DELETE', '/api/rutinas/{id}', [], $foreign['routine']->id, null],
        ];
        if ($module === 'family') {
            $cases[] = ['GET', '/api/family/older-adults/{id}/incidents', [], $foreign['adult']->id, null];
            $cases[] = ['GET', '/api/family/incidents?older_adult_id={id}', [], $foreign['adult']->id, null];
        } else {
            $cases = array_merge($cases, [
                ['GET', '/api/professional/routine-notes?older_adult_id={id}', [], $foreign['adult']->id, null],
                ['POST', '/api/professional/routine-notes', ['content' => 'Indebida'], $foreign['adult']->id, 'older_adult_id'],
                ['GET', '/api/professional/routine-notes/{id}', [], $foreign['note']->id, null],
                ['PUT', '/api/professional/routine-notes/{id}', ['content' => 'Indebida'], $foreign['note']->id, null],
                ['DELETE', '/api/professional/routine-notes/{id}', [], $foreign['note']->id, null],
                ['POST', '/api/professional/incidents', ['title' => 'Indebido'], $foreign['adult']->id, 'older_adult_id'],
                ['PATCH', '/api/professional/incidents/{id}', ['description' => 'Indebida'], $foreign['incident']->id, null],
                ['POST', '/api/medications/{id}/taken', [], $foreign['assignment']->id, null],
                ['PUT', '/api/schedules/{id}', ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '16:00'], $foreign['schedule']->id, null],
                ['POST', '/api/schedules/{id}/change-request', ['start_time' => '09:00', 'end_time' => '17:00', 'message' => 'Indebida'], $foreign['schedule']->id, null],
            ]);
        }

        foreach ($cases as [$method, $path, $payload, $foreignId, $bodyKey]) {
            foreach ([$foreignId, 999999] as $id) {
                $body = $bodyKey ? [...$payload, $bodyKey => $id] : $payload;
                $this->json($method, str_replace('{id}', (string) $id, $path), $body, $headers)
                    ->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
            }
        }

        $this->assertDatabaseHas('routine_notes', ['id' => $foreign['note']->id, 'content' => 'Nota privada']);
        $this->assertDatabaseHas('rutinas', ['id' => $foreign['routine']->id, 'nombre' => 'Rutina privada']);
        $this->assertDatabaseHas('incidents', ['id' => $foreign['incident']->id, 'description' => 'Descripcion privada']);
        $this->assertDatabaseHas('caregiver_schedules', ['id' => $foreign['schedule']->id, 'notes' => 'Horario privado']);
        $this->assertDatabaseCount('medication_administrations', 0);
        $this->assertDatabaseCount('routine_notes', 1);
        $this->assertDatabaseCount('rutinas', 1);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('vacation_requests', 1);
    }

    public static function caregiverRoles(): array
    {
        return [
            ['familiar', 'family'], ['cuidador_familiar', 'family'],
            ['profesional', 'professional'], ['cuidador_profesional', 'professional'],
        ];
    }

    #[DataProvider('allRoles')]
    public function test_roles_cannot_escalate_to_other_modules_or_discover_resource_existence(string $role): void
    {
        $actor = $this->user($role);
        $foreign = $this->resources();
        $headers = $this->headersFor($actor);
        if ($role === 'admin') {
            $cases = [
                ['GET', '/api/professional/routine-notes/{id}', $foreign['note']->id],
                ['GET', '/api/family/older-adults/{id}', $foreign['adult']->id],
                ['POST', '/api/medications/{id}/taken', $foreign['assignment']->id],
            ];
        } else {
            $cases = [
                ['GET', '/api/admin/users/{id}', $foreign['professional']->id],
                ['PUT', '/api/admin/users/{id}', $foreign['professional']->id],
                ['PATCH', '/api/admin/users/{id}/approve', $foreign['professional']->id],
                ['DELETE', '/api/admin/users/{id}', $foreign['professional']->id],
                ['GET', '/api/admin/older-adults/{id}', $foreign['adult']->id],
                ['DELETE', '/api/admin/older-adults/{id}', $foreign['adult']->id],
                ['PATCH', '/api/admin/medications/inventory/{id}/stock', $foreign['assignment']->id],
                ['PATCH', '/api/admin/vacation-requests/{id}/approve', $foreign['vacation']->id],
            ];
            $cases[] = str_contains($role, 'familiar')
                ? ['GET', '/api/professional/routine-notes/{id}', $foreign['note']->id]
                : ['GET', '/api/family/older-adults/{id}', $foreign['adult']->id];
        }

        foreach ($cases as [$method, $path, $foreignId]) {
            $existingResponse = $this->json($method, str_replace('{id}', (string) $foreignId, $path), [
                'role' => 'admin', 'is_approved' => true, 'name' => 'Escalamiento', 'action' => 'increase', 'amount' => 999,
            ], $headers)->assertForbidden();
            $missingResponse = $this->json($method, str_replace('{id}', '999999', $path), [], $headers)->assertForbidden();
            $this->assertSame($existingResponse->json(), $missingResponse->json());
        }

        $this->assertDatabaseHas('users', ['id' => $foreign['professional']->id, 'role' => 'profesional', 'is_approved' => true]);
        $this->assertDatabaseHas('older_adults', ['id' => $foreign['adult']->id]);
        $this->assertDatabaseHas('vacation_requests', ['id' => $foreign['vacation']->id, 'status' => 'pending']);
        $this->assertDatabaseHas('older_adult_medications', ['id' => $foreign['assignment']->id, 'quantity' => 5]);
    }

    public static function allRoles(): array
    {
        return [['admin'], ['profesional'], ['familiar'], ['cuidador_profesional'], ['cuidador_familiar']];
    }

    public function test_professional_operations_reject_injected_fields_and_accept_clean_payloads(): void
    {
        $own = $this->resources();
        $foreign = $this->resources();
        $headers = $this->headersFor($own['professional']);
        $injected = [
            'created_by' => $foreign['professional']->id, 'professional_caregiver_id' => $foreign['professional']->id,
            'user_id' => $foreign['professional']->id, 'reported_by' => $foreign['professional']->id,
            'recorded_by' => $foreign['professional']->id, 'reviewed_by' => $foreign['professional']->id,
            'role' => 'admin', 'is_approved' => true, 'id' => 999999,
        ];

        $this->putJson("/api/professional/routine-notes/{$own['note']->id}", [
            ...$injected, 'content' => 'Nota permitida', 'older_adult_id' => $foreign['adult']->id,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $this->putJson("/api/professional/routine-notes/{$own['note']->id}", ['content' => 'Nota permitida'], $headers)->assertOk();
        $this->putJson("/api/rutinas/{$own['routine']->id}", [
            ...$injected, 'older_adult_id' => $foreign['adult']->id,
            'nombre' => 'Rutina permitida', 'horario' => '10:00', 'actividades' => ['Caminar'],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $this->putJson("/api/rutinas/{$own['routine']->id}", [
            'nombre' => 'Rutina permitida', 'horario' => '10:00', 'actividades' => ['Caminar'],
        ], $headers)->assertOk();
        $this->patchJson("/api/professional/incidents/{$own['incident']->id}", [
            ...$injected, 'older_adult_id' => $foreign['adult']->id, 'description' => 'Nota permitida', 'incident_date' => '2000-01-01',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $this->patchJson("/api/professional/incidents/{$own['incident']->id}", ['description' => 'Nota permitida'], $headers)->assertOk();
        $this->putJson("/api/schedules/{$own['schedule']->id}", [
            ...$injected, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00',
            'change_request_status' => 'approved', 'change_request_message' => 'Inyectado',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('change_request_status');
        $this->putJson("/api/schedules/{$own['schedule']->id}", ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'], $headers)->assertOk();
        $this->postJson('/api/professional/vacation-requests', [
            ...$injected, 'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'reason' => 'Descanso',
            'status' => 'approved', 'reviewed_at' => now()->toISOString(),
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/api/professional/vacation-requests', [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'reason' => 'Descanso',
        ], $headers)->assertCreated();
        $this->postJson("/api/medications/{$own['assignment']->id}/taken", [
            ...$injected, 'older_adult_id' => $foreign['adult']->id, 'administration_type' => 'extra', 'notes' => 'Toma permitida',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('administration_type');
        $this->postJson("/api/medications/{$own['assignment']->id}/taken", ['notes' => 'Toma permitida'], $headers)->assertOk();

        $this->assertDatabaseHas('routine_notes', [
            'id' => $own['note']->id, 'older_adult_id' => $own['adult']->id, 'professional_caregiver_id' => $own['professional']->id,
        ]);
        $this->assertDatabaseHas('rutinas', ['id' => $own['routine']->id, 'older_adult_id' => $own['adult']->id, 'created_by' => $own['professional']->id]);
        $this->assertDatabaseHas('incidents', ['id' => $own['incident']->id, 'older_adult_id' => $own['adult']->id, 'reported_by' => $own['professional']->id]);
        $this->assertDatabaseHas('caregiver_schedules', ['id' => $own['schedule']->id, 'user_id' => $own['professional']->id, 'change_request_status' => null]);
        $this->assertDatabaseHas('vacation_requests', ['user_id' => $own['professional']->id, 'reason' => 'Descanso', 'status' => 'pending', 'reviewed_by' => null]);
        $this->assertDatabaseHas('medication_administrations', [
            'older_adult_id' => $own['adult']->id, 'recorded_by' => $own['professional']->id, 'administration_type' => 'scheduled',
        ]);
        $this->assertDatabaseHas('routine_notes', ['id' => $foreign['note']->id, 'content' => 'Nota privada']);
        $this->assertDatabaseHas('users', ['id' => $own['professional']->id, 'role' => 'profesional']);
    }

    public function test_admin_can_manage_other_creators_adults_without_changing_server_owned_creator(): void
    {
        $admin = $this->user('admin');
        $foreign = $this->resources();
        $headers = $this->headersFor($admin);

        $this->getJson("/api/admin/older-adults/{$foreign['adult']->id}", $headers)->assertOk();
        $this->putJson("/api/admin/older-adults/{$foreign['adult']->id}", [
            'full_name' => 'Cambio administrativo permitido', 'created_by' => $admin->id, 'id' => 999999,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['created_by', 'id']);
        $this->putJson("/api/admin/older-adults/{$foreign['adult']->id}", ['full_name' => 'Cambio administrativo permitido'], $headers)->assertOk();
        $this->assertDatabaseHas('older_adults', ['id' => $foreign['adult']->id, 'created_by' => $foreign['professional']->id]);

        $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Alta administrativa', 'created_by' => $foreign['professional']->id,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $createdId = $this->postJson('/api/admin/older-adults', ['full_name' => 'Alta administrativa'], $headers)->assertCreated()->json('older_adult.id');
        $this->assertDatabaseHas('older_adults', ['id' => $createdId, 'created_by' => $admin->id]);
    }

    public function test_nested_medication_identifier_must_belong_to_the_adult_being_updated(): void
    {
        $admin = $this->user('admin');
        $own = $this->resources();
        $foreign = $this->resources();
        $headers = $this->headersFor($admin);

        $this->putJson("/api/admin/older-adults/{$own['adult']->id}", [
            'full_name' => 'Cambio que debe revertirse',
            'medications' => [['id' => $foreign['assignment']->id, 'name' => 'Medicamento inyectado', 'dosage' => '999']],
        ], $headers)->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);

        $this->assertDatabaseHas('older_adults', ['id' => $own['adult']->id, 'full_name' => 'Adulto privado']);
        $this->assertDatabaseHas('older_adult_medications', ['id' => $own['assignment']->id, 'older_adult_id' => $own['adult']->id, 'quantity' => 5]);
        $this->assertDatabaseHas('older_adult_medications', ['id' => $foreign['assignment']->id, 'older_adult_id' => $foreign['adult']->id, 'quantity' => 5]);
        $this->assertDatabaseMissing('medications', ['name' => 'Medicamento inyectado']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_approved' => true]);
    }

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('idor-test')->plainTextToken];
    }

    private function resources(): array
    {
        $professional = $this->user('profesional');
        $family = $this->user('familiar');
        $adult = OlderAdult::create([
            'full_name' => 'Adulto privado', 'status' => 'Estable', 'created_by' => $professional->id,
            'professional_caregiver_id' => $professional->id, 'family_caregiver_id' => $family->id,
        ]);
        $routine = Rutina::create([
            'older_adult_id' => $adult->id, 'created_by' => $professional->id,
            'nombre' => 'Rutina privada', 'horario' => '09:00', 'actividades' => ['Caminar'],
        ]);
        $note = RoutineNote::create([
            'older_adult_id' => $adult->id, 'professional_caregiver_id' => $professional->id, 'content' => 'Nota privada', 'note_date' => now()->toDateString(),
        ]);
        $incident = Incident::create([
            'title' => 'Incidente privado', 'description' => 'Descripcion privada', 'older_adult_id' => $adult->id,
            'reported_by' => $professional->id, 'incident_date' => now()->toDateString(),
        ]);
        $medication = Medication::firstOrCreate(['name' => 'Medicamento privado'], ['is_active' => true]);
        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $adult->id, 'medication_id' => $medication->id, 'dosage' => '1 tableta', 'schedule' => '09:00', 'quantity' => 5, 'is_active' => true,
        ]);
        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '16:00', 'notes' => 'Horario privado',
        ]);
        $vacation = VacationRequest::create([
            'user_id' => $professional->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'reason' => 'Privada', 'status' => 'pending',
        ]);

        return compact('professional', 'family', 'adult', 'routine', 'note', 'incident', 'assignment', 'schedule', 'vacation');
    }
}

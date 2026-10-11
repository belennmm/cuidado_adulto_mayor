<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\MedicationAcquisition;
use App\Models\Rutina;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\AdminUserService;
use App\Services\CaregiverScheduleService;
use App\Services\RoutineService;
use App\Services\VacationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class ProcessStateIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_approved' => true]);
    }

    public function test_stale_pending_account_cannot_be_rejected_after_approval(): void
    {
        $pending = User::factory()->pendingProfessional()->create();
        $service = app(AdminUserService::class);
        $service->approve($pending);
        try {
            $service->reject($pending);
            $this->fail('Una instancia desactualizada no debe permitir rechazar la cuenta aprobada.');
        } catch (HttpResponseException $error) {
            $this->assertSame(422, $error->getResponse()->getStatusCode());
        }
        $this->assertTrue($pending->fresh()->is_approved);
    }

    public function test_repeated_account_approval_is_a_no_op(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00'));
        $pending = User::factory()->pendingProfessional()->create();
        $service = app(AdminUserService::class);
        $approved = $service->approve($pending);
        $timestamp = $approved->updated_at->toISOString();
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));
        $this->assertSame($timestamp, $service->approve($pending)->updated_at->toISOString());
    }

    public function test_user_cannot_approve_their_own_pending_account(): void
    {
        $pending = User::factory()->pendingProfessional()->create();
        Sanctum::actingAs($pending);
        $this->putJson('/api/me', ['name' => $pending->name, 'is_approved' => true])->assertForbidden();
        $this->assertFalse($pending->fresh()->is_approved);
    }

    public function test_incident_advances_and_rejects_regressions_atomically(): void
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        Sanctum::actingAs($professional);
        $id = $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $adult->id, 'title' => 'Incidente', 'description' => 'Nota original',
        ])->assertCreated()->json('incident.id');
        foreach (['en_progreso', 'resuelto', 'cerrado', 'cerrado'] as $status) {
            $this->patchJson("/api/professional/incidents/{$id}", ['status' => $status])->assertOk();
        }
        $this->patchJson("/api/professional/incidents/{$id}", [
            'status' => 'abierto', 'description' => 'Cambio rechazado',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'cerrado', 'description' => 'Nota original']);
    }

    public function test_incident_rejects_unknown_states_and_forged_initial_state(): void
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        Sanctum::actingAs($professional);
        $payload = ['older_adult_id' => $adult->id, 'title' => 'Incidente'];
        $this->postJson('/api/professional/incidents', [...$payload, 'status' => 'cerrado'])->assertUnprocessable();
        $id = $this->postJson('/api/professional/incidents', $payload)->assertCreated()->json('incident.id');
        $this->patchJson("/api/professional/incidents/{$id}", ['status' => 'aprobado'])->assertUnprocessable();
        $this->assertSame('abierto', Incident::findOrFail($id)->status);
    }

    public function test_direct_incident_resolution_remains_allowed(): void
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        Sanctum::actingAs($professional);
        $id = $this->postJson('/api/professional/incidents', [
            'older_adult_id' => $adult->id, 'title' => 'Incidente',
        ])->assertCreated()->json('incident.id');
        $this->patchJson("/api/professional/incidents/{$id}", ['status' => 'resuelto'])->assertOk();
        $this->patchJson("/api/professional/incidents/{$id}", ['status' => 'en_progreso'])->assertUnprocessable();
    }

    public function test_legacy_incident_states_preserve_note_editing_and_support_canonical_advancement(): void
    {
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        Sanctum::actingAs($professional);
        foreach (['pendiente', 'en seguimiento'] as $legacy) {
            $incident = Incident::create([
                'older_adult_id' => $adult->id, 'title' => 'Legado', 'adult_name' => $adult->full_name,
                'status' => $legacy, 'severity' => 'media', 'incident_date' => '2026-05-04',
                'incident_time' => '10:00:00', 'reported_by' => $professional->id,
            ]);
            $this->patchJson("/api/professional/incidents/{$incident->id}", ['description' => 'Nota nueva'])
                ->assertOk()->assertJsonPath('incident.status', $legacy);
            $this->patchJson("/api/professional/incidents/{$incident->id}", ['status' => 'resuelto'])->assertOk();
        }
    }

    public function test_medication_cannot_be_recorded_outside_its_active_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00'));
        $professional = $this->createApprovedProfessional();
        $adult = $this->createAssignedOlderAdult($professional);
        Sanctum::actingAs($professional);
        foreach ([['is_active' => false], ['start_date' => '2026-05-05'], ['end_date' => '2026-05-03'], ['days' => ['martes']]] as $attributes) {
            $assignment = $this->createMedicationAssignment($adult, $attributes);
            $this->postJson("/api/medications/{$assignment->id}/taken")->assertUnprocessable();
        }
        $this->assertDatabaseCount('medication_administrations', 0);
        $assignment = $this->createMedicationAssignment($adult, ['days' => ['lunes'], 'start_date' => '2026-05-04', 'end_date' => '2026-05-04']);
        $this->postJson("/api/medications/{$assignment->id}/taken")->assertOk();
    }

    public function test_medication_retry_preserves_original_record_even_if_assignment_was_disabled(): void
    {
        $professional = $this->createApprovedProfessional();
        $assignment = $this->createMedicationAssignment($this->createAssignedOlderAdult($professional));
        Sanctum::actingAs($professional);
        $id = $this->postJson("/api/medications/{$assignment->id}/taken", ['notes' => 'Original'])->assertOk()->json('administration.id');
        $assignment->update(['is_active' => false]);
        $this->postJson("/api/medications/{$assignment->id}/taken", ['notes' => 'Reintento'])
            ->assertOk()->assertJsonPath('administration.id', $id)->assertJsonPath('administration.notes', 'Original');
        $this->assertDatabaseCount('medication_administrations', 1);
    }

    public function test_stock_and_acquisition_are_rolled_back_together_on_partial_failure(): void
    {
        $assignment = $this->createMedicationAssignment($this->createOlderAdult(), ['quantity' => 30]);
        Sanctum::actingAs($this->admin());
        $event = 'eloquent.creating: '.MedicationAcquisition::class;
        Event::listen($event, fn () => throw new RuntimeException('Fallo simulado en la adquisición'));
        try {
            $this->patchJson("/api/admin/medications/inventory/{$assignment->id}/stock", [
                'action' => 'increase', 'amount' => 5,
            ])->assertServerError()->assertDontSee('Fallo simulado');
        } finally {
            Event::forget($event);
        }
        $this->assertSame(30, $assignment->fresh()->quantity);
        $this->assertDatabaseCount('medication_acquisitions', 0);
    }

    public function test_vacation_review_uses_current_state_even_with_a_stale_model(): void
    {
        $professional = $this->createApprovedProfessional();
        $request = VacationRequest::create([
            'user_id' => $professional->id, 'start_date' => '2030-01-01', 'end_date' => '2030-01-02',
            'reason' => 'Descanso', 'status' => 'pending',
        ]);
        $admin = $this->admin();
        $service = app(VacationRequestService::class);
        $service->review($request, $admin, 'approved');
        try {
            $service->review($request, $admin, 'rejected');
            $this->fail('Una solicitud revisada no debe admitir otra decisión.');
        } catch (HttpResponseException $error) {
            $this->assertSame(422, $error->getResponse()->getStatusCode());
        }
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->reviewed_by);
    }

    public function test_vacation_service_rejects_a_non_decision_state(): void
    {
        $request = VacationRequest::create([
            'user_id' => $this->createApprovedProfessional()->id, 'start_date' => '2030-01-01',
            'end_date' => '2030-01-02', 'reason' => 'Descanso', 'status' => 'pending',
        ]);
        try {
            app(VacationRequestService::class)->review($request, $this->admin(), 'pending');
            $this->fail('La revisión debe aprobar o rechazar.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('status', $error->errors());
        }
        $this->assertNull($request->fresh()->reviewed_by);
    }

    public function test_vacation_creation_cannot_supply_a_reviewed_state_or_omit_required_reason(): void
    {
        $professional = $this->createApprovedProfessional();
        Sanctum::actingAs($professional);
        $dates = ['start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDays(3)->toDateString()];
        $this->postJson('/api/professional/vacation-requests', [
            ...$dates, 'reason' => 'Descanso', 'status' => 'approved', 'reviewed_by' => $professional->id,
        ])->assertUnprocessable();
        $this->postJson('/api/professional/vacation-requests', $dates)->assertUnprocessable();
        $this->assertDatabaseCount('vacation_requests', 0);
    }

    public function test_pending_schedule_change_cannot_be_overwritten_through_any_write_path(): void
    {
        $professional = $this->createApprovedProfessional();
        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
        ]);
        Sanctum::actingAs($professional);
        $proposal = ['start_time' => '09:00', 'end_time' => '17:00', 'message' => 'Solicitud original'];
        $this->postJson("/api/schedules/{$schedule->id}/change-request", $proposal)->assertOk();
        $this->postJson("/api/schedules/{$schedule->id}/change-request", [...$proposal, 'message' => 'Reemplazo'])->assertUnprocessable();
        $data = ['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '18:00'];
        $this->putJson("/api/schedules/{$schedule->id}", $data)->assertUnprocessable();
        $this->postJson('/api/schedules', $data)->assertUnprocessable();
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/schedules', [...$data, 'user_id' => $professional->id])->assertUnprocessable();
        $this->assertSame('Solicitud original', $schedule->fresh()->change_request_message);
        $this->assertSame('08:00:00', $schedule->fresh()->start_time);
    }

    public function test_schedule_review_cannot_skip_request_or_repeat_a_completed_decision(): void
    {
        $professional = $this->createApprovedProfessional();
        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '16:00:00',
        ]);
        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/admin/schedules/{$schedule->id}/change-request/approve")->assertUnprocessable();
        $service = app(CaregiverScheduleService::class);
        $service->requestChange($professional, $schedule, ['start_time' => '09:00', 'end_time' => '17:00', 'message' => 'Cambio']);
        $stale = $schedule->fresh();
        $service->approveChange($stale);
        try {
            $service->rejectChange($stale);
            $this->fail('La revisión debe usar el estado actual.');
        } catch (HttpResponseException $error) {
            $this->assertSame(422, $error->getResponse()->getStatusCode());
        }
        $this->assertNull($schedule->fresh()->change_request_status);
        $this->assertSame('09:00', substr($schedule->fresh()->start_time, 0, 5));
    }

    public function test_routine_requires_all_activities_and_preserves_completion_on_stale_retries(): void
    {
        $professional = $this->createApprovedProfessional();
        $routine = Rutina::create([
            'older_adult_id' => $this->createAssignedOlderAdult($professional)->id,
            'created_by' => $professional->id, 'nombre' => 'Rutina', 'horario' => '08:00', 'actividades' => ['A', 'B'],
        ]);
        $service = app(RoutineService::class);
        $first = $service->completeActivity($routine, ['actividad_index' => 1]);
        $this->assertFalse($first->completada);
        $completed = $service->completeActivity($routine, ['actividad_index' => 0]);
        $this->assertTrue($completed->completada);
        $original = $completed->actividades_completadas;
        $retried = $service->completeActivity($routine, ['actividad_index' => 1]);
        $this->assertSame($original, $retried->actividades_completadas);
        $this->assertSame($completed->completada_at->toISOString(), $retried->completada_at->toISOString());
        Sanctum::actingAs($professional);
        $this->patchJson("/api/rutinas/{$routine->id}/completar", ['actividad_index' => 99])->assertUnprocessable();
        $this->patchJson("/api/rutinas/{$routine->id}/completar", [])->assertUnprocessable();
        $this->patchJson("/api/rutinas/{$routine->id}/completar", ['completada' => true])->assertUnprocessable();
        $this->assertSame($original, $routine->fresh()->actividades_completadas);
    }
}

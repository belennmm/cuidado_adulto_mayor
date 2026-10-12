<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdministrativeAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('channel')->with('security_audit')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context) {
            $this->assertSame('security.event', $message);
            $this->events[] = $context;
        });
    }

    public function test_admin_list_access_is_audited_without_returned_personal_data(): void
    {
        $admin = $this->admin();
        $response = $this->getJson('/api/admin/users')->assertOk();

        $this->assertEvent($response, 'administrative.action', 'read', 'route', 'api/admin/users', $admin->id);
        $this->assertStringNotContainsString($admin->email, json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_schedule_creation_records_the_new_id(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->approvedProfessional()->create();
        $response = $this->postJson('/api/admin/schedules', [
            'user_id' => $professional->id, 'day_of_week' => 1,
            'start_time' => '08:00', 'end_time' => '16:00', 'notes' => 'Nota privada del turno',
        ])->assertCreated();

        $this->assertEvent($response, 'administrative.action', 'create', 'caregiver_schedule', $response->json('schedule.id'), $admin->id);
        $this->assertStringNotContainsString('Nota privada', json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_schedule_deletion_keeps_the_resource_id(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->approvedProfessional()->create();
        $schedule = CaregiverSchedule::create([
            'user_id' => $professional->id, 'day_of_week' => 1,
            'start_time' => '08:00:00', 'end_time' => '16:00:00',
        ]);
        $response = $this->deleteJson('/api/admin/schedules/'.$schedule->id)->assertOk();

        $this->assertEvent($response, 'resource.deleted', 'delete', 'caregiver_schedule', $schedule->id, $admin->id);
        $this->assertModelMissing($schedule);
    }

    public function test_vacation_approval_is_audited_without_the_reason(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->approvedProfessional()->create();
        $vacation = VacationRequest::create([
            'user_id' => $professional->id, 'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(), 'status' => 'pending', 'reason' => 'Motivo privado',
        ]);
        $response = $this->patchJson('/api/admin/vacation-requests/'.$vacation->id.'/approve')->assertOk();

        $this->assertEvent($response, 'administrative.action', 'approve', 'vacation_request', $vacation->id, $admin->id);
        $this->assertStringNotContainsString('Motivo privado', json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_existing_user_audit_is_not_duplicated(): void
    {
        $this->admin();
        $target = User::factory()->approvedFamily()->create();
        $this->deleteJson('/api/admin/users/'.$target->id)->assertOk();

        $this->assertCount(1, $this->events);
        $this->assertSame('user.delete', $this->events[0]['event']);
    }

    public function test_admin_downloads_are_audited_without_the_filename_or_file_content(): void
    {
        $admin = $this->admin();
        $this->registerExportRoute();
        $response = $this->get('/api/admin/audit-export')->assertOk();

        $this->assertEvent($response, 'administrative.export', 'export', 'route', 'api/admin/audit-export', $admin->id);
        $serialized = json_encode($this->events, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('paciente-privado.csv', $serialized);
        $this->assertStringNotContainsString('contenido-clinico-privado', $serialized);
    }

    public function test_non_admin_download_attempts_only_record_the_authorization_denial(): void
    {
        Sanctum::actingAs(User::factory()->approvedFamily()->create(), ['*']);
        $this->registerExportRoute();
        $this->getJson('/api/admin/audit-export')->assertForbidden();

        $this->assertCount(1, $this->events);
        $this->assertSame('authorization.denied', $this->events[0]['event']);
    }

    public function test_invalid_administrative_changes_do_not_claim_success(): void
    {
        $this->admin();
        $this->postJson('/api/admin/schedules', [])->assertUnprocessable();

        $this->assertSame([], $this->events);
    }

    private function registerExportRoute(): void
    {
        Route::get('/api/admin/audit-export', fn () => response()->streamDownload(
            fn () => print('contenido-clinico-privado'),
            'paciente-privado.csv',
            ['Content-Type' => 'text/csv'],
        ))->middleware(['api', 'auth:sanctum', 'admin'])->can('viewAny', User::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function assertEvent(TestResponse $response, string $name, string $action, string $type, int|string $id, int $actor): void
    {
        $this->assertCount(1, $this->events);
        $event = $this->events[0];
        $this->assertSame($name, $event['event']);
        $this->assertSame($action, $event['action']);
        $this->assertSame($type, $event['resource_type']);
        $this->assertSame($id, $event['resource_id']);
        $this->assertSame($actor, $event['actor_id']);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $event['correlation_id']);
        $this->assertNotEmpty($event['correlation_id']);
    }
}

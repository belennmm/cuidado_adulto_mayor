<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensitiveDataAuditTest extends TestCase
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

    public function test_reading_a_clinical_profile_logs_identifiers_without_clinical_content(): void
    {
        $admin = User::factory()->admin()->create();
        $adult = OlderAdult::factory()->create([
            'medical_history' => 'Historial clinico privado',
            'allergies' => 'Alergia confidencial',
        ]);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/older-adults/'.$adult->id)->assertOk();

        $this->assertEvent($response, 'read', 'older_adult', $adult->id, $admin->id);
        $serialized = json_encode($this->events, JSON_THROW_ON_ERROR);
        foreach ([$adult->full_name, $adult->medical_history, $adult->allergies] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $serialized);
        }
    }

    public function test_a_collection_access_does_not_copy_search_values_or_returned_records(): void
    {
        $admin = User::factory()->admin()->create();
        OlderAdult::factory()->create(['full_name' => 'Persona Privada']);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/older-adults?q=Persona')->assertOk();

        $this->assertEvent($response, 'read', 'older_adult', null, $admin->id);
        $this->assertStringNotContainsString('Persona', json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_creation_logs_the_new_resource_id(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Adulto Nuevo', 'age' => 74, 'gender' => 'Masculino',
            'room' => 'A-101', 'status' => 'Estable',
        ])->assertCreated();

        $this->assertEvent($response, 'create', 'older_adult', $response->json('older_adult.id'), $admin->id);
    }

    public function test_updating_a_clinical_profile_logs_the_change_after_persistence(): void
    {
        $admin = User::factory()->admin()->create();
        $adult = OlderAdult::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->putJson('/api/admin/older-adults/'.$adult->id, [
            'full_name' => 'Adulto Actualizado', 'age' => 75, 'gender' => 'Masculino',
            'room' => 'A-102', 'status' => 'Estable', 'medical_history' => 'Historia actualizada privada',
        ])->assertOk();

        $this->assertEvent($response, 'update', 'older_adult', $adult->id, $admin->id);
        $this->assertSame('Historia actualizada privada', $adult->fresh()->medical_history);
        $this->assertStringNotContainsString('Historia actualizada privada', json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_deletion_keeps_the_resource_id_in_the_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $adult = OlderAdult::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->deleteJson('/api/admin/older-adults/'.$adult->id)->assertOk();

        $this->assertEvent($response, 'delete', 'older_adult', $adult->id, $admin->id);
        $this->assertDatabaseMissing('older_adults', ['id' => $adult->id]);
    }

    public function test_a_family_caregiver_read_is_also_audited(): void
    {
        $family = User::factory()->approvedFamily()->create();
        $adult = OlderAdult::factory()->assignedToFamily($family)->create();
        Sanctum::actingAs($family, ['*']);

        $response = $this->getJson('/api/family/older-adults/'.$adult->id)->assertOk();

        $this->assertEvent($response, 'read', 'older_adult', $adult->id, $family->id);
    }

    public function test_a_professional_note_change_is_audited_without_its_content(): void
    {
        $professional = User::factory()->approvedProfessional()->create();
        $adult = OlderAdult::factory()->assignedToProfessional($professional)->create();
        $note = RoutineNote::create([
            'older_adult_id' => $adult->id, 'professional_caregiver_id' => $professional->id,
            'content' => 'Nota anterior', 'note_date' => '2026-10-11',
        ]);
        Sanctum::actingAs($professional, ['*']);

        $response = $this->putJson('/api/professional/routine-notes/'.$note->id, [
            'content' => 'Observacion privada del paciente',
        ])->assertOk();

        $this->assertEvent($response, 'update', 'routine_note', $note->id, $professional->id);
        $this->assertStringNotContainsString('Observacion privada', json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_validation_errors_do_not_claim_a_successful_change(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);

        $this->postJson('/api/admin/older-adults', [])->assertUnprocessable();

        $this->assertSame([], $this->events);
    }

    public function test_access_denials_remain_authorization_events_without_a_successful_read(): void
    {
        Sanctum::actingAs(User::factory()->approvedFamily()->create(), ['*']);

        $this->getJson('/api/admin/older-adults')->assertForbidden();

        $this->assertCount(1, $this->events);
        $this->assertSame('authorization.denied', $this->events[0]['event']);
    }

    private function assertEvent(TestResponse $response, string $action, string $type, ?int $id, int $actor): void
    {
        $this->assertCount(1, $this->events);
        $event = $this->events[0];
        $this->assertSame($action === 'read' ? 'sensitive_data.access' : 'sensitive_data.change', $event['event']);
        $this->assertSame('success', $event['result']);
        $this->assertSame($action, $event['action']);
        $this->assertSame($type, $event['resource_type']);
        $this->assertSame($id, $event['resource_id']);
        $this->assertSame($actor, $event['actor_id']);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $event['correlation_id']);
        $this->assertNotEmpty($event['correlation_id']);
    }
}

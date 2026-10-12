<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountMedicationAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        Log::shouldReceive('channel')->with('security_audit')->andReturnSelf();
        Log::shouldReceive('log')->andReturnUsing(function (string $level, string $message, array $context) {
            $this->assertSame($context['result'] === 'success' ? 'info' : 'warning', $level);
            $this->assertSame('security.event', $message);
            $this->events[] = $context;
        });
    }

    public function test_admin_user_creation_records_actor_and_target_without_personal_data(): void
    {
        $admin = $this->admin();
        $response = $this->postJson('/api/admin/users', [
            'name' => 'Usuario Privado', 'email' => 'privado@example.com',
            'password' => 'Secure-Test!123', 'role' => 'familiar',
        ])->assertCreated();

        $this->assertEvents($response, ['user.create'], $response->json('user.id'), $admin->id);
        $serialized = json_encode($this->events, JSON_THROW_ON_ERROR);
        foreach (['Usuario Privado', 'privado@example.com', 'Secure-Test!123'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $serialized);
        }
    }

    public function test_actual_role_and_approval_changes_have_distinct_correlated_events(): void
    {
        $admin = $this->admin();
        $target = User::factory()->approvedFamily()->create();
        $response = $this->putJson('/api/admin/users/'.$target->id, [
            'name' => $target->name, 'email' => $target->email,
            'role' => 'profesional', 'is_approved' => false,
        ])->assertOk();

        $this->assertEvents($response, ['user.update', 'user.role_changed', 'account.status_changed'], $target->id, $admin->id);
        $this->assertSame('profesional', $target->fresh()->role);
        $this->assertFalse($target->fresh()->is_approved);
    }

    public function test_unchanged_roles_and_approval_do_not_claim_a_security_state_change(): void
    {
        $admin = $this->admin();
        $target = User::factory()->approvedFamily()->create();
        $response = $this->putJson('/api/admin/users/'.$target->id, [
            'name' => 'Nombre Modificado', 'email' => $target->email,
            'role' => 'familiar', 'is_approved' => true,
        ])->assertOk();

        $this->assertEvents($response, ['user.update'], $target->id, $admin->id);
    }

    public function test_account_approval_is_recorded(): void
    {
        $admin = $this->admin();
        $target = User::factory()->pendingProfessional()->create();
        $response = $this->patchJson('/api/admin/users/'.$target->id.'/approve')->assertOk();

        $this->assertEvents($response, ['user.approve', 'account.status_changed'], $target->id, $admin->id);
    }

    public function test_pending_account_rejection_keeps_the_deleted_target_id(): void
    {
        $admin = $this->admin();
        $target = User::factory()->pendingProfessional()->create();
        $response = $this->deleteJson('/api/admin/users/'.$target->id.'/reject')->assertOk();

        $this->assertEvents($response, ['user.reject'], $target->id, $admin->id);
        $this->assertModelMissing($target);
    }

    public function test_user_deletion_is_recorded(): void
    {
        $admin = $this->admin();
        $target = User::factory()->approvedFamily()->create();
        $response = $this->deleteJson('/api/admin/users/'.$target->id)->assertOk();

        $this->assertEvents($response, ['user.delete'], $target->id, $admin->id);
        $this->assertModelMissing($target);
    }

    public function test_public_registration_has_a_target_but_no_authenticated_actor(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Nuevo Usuario', 'email' => 'registro-audit@example.com',
            'password' => 'Secure-Test!123', 'role' => 'familiar', 'privacy_consent' => true,
        ])->assertStatus(202)->assertJsonMissingPath('user');

        $target = User::query()->where('email', 'registro-audit@example.com')->sole();
        $this->assertEvents($response, ['account.create'], $target->id, null);
    }

    public function test_duplicate_registration_does_not_claim_an_account_was_created(): void
    {
        $user = User::factory()->approvedFamily()->create();

        $this->postJson('/api/register', [
            'name' => 'Nuevo Usuario', 'email' => $user->email,
            'password' => 'Secure-Test!123', 'role' => 'familiar', 'privacy_consent' => true,
        ])->assertStatus(202)->assertJsonMissingPath('user');

        $this->assertSame([], $this->events);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_profile_updates_are_audited(): void
    {
        $user = User::factory()->approvedFamily()->create();
        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson('/api/me', [
            'name' => 'Perfil Actualizado', 'email' => $user->email,
            'current_password' => 'password',
        ])->assertOk();

        $this->assertEvents($response, ['account.update'], $user->id, $user->id);
    }

    public function test_medication_creation_update_stock_adjustment_and_deletion_are_audited(): void
    {
        $this->withoutExceptionHandling();
        $admin = $this->admin();
        $payload = [
            'name' => 'Medicamento Privado', 'presentation' => 'Tabletas', 'quantity' => 10,
            'unit' => 'tabletas', 'minimum_stock' => 2, 'older_adult_id' => null,
            'expiration_date' => null,
        ];
        $response = $this->postJson('/api/admin/medications/inventory', $payload)->assertCreated();
        $id = $response->json('medication.id');
        $this->assertEvents($response, ['medication_inventory.create'], $id, $admin->id);
        $this->assertStringNotContainsString('Medicamento Privado', json_encode($this->events, JSON_THROW_ON_ERROR));

        $this->events = [];
        $response = $this->putJson('/api/admin/medications/inventory/'.$id, [
            ...$payload, 'quantity' => 12,
        ])->assertOk();
        $this->assertEvents($response, ['medication_inventory.update'], $id, $admin->id);

        foreach (['increase', 'decrease'] as $direction) {
            $this->events = [];
            $response = $this->patchJson('/api/admin/medications/inventory/'.$id.'/stock', [
                'action' => $direction, 'amount' => 2,
            ])->assertOk();
            $this->assertEvents($response, ['medication_inventory.adjust_stock'], $id, $admin->id);
        }

        $this->events = [];
        $response = $this->deleteJson('/api/admin/medications/inventory/'.$id)->assertOk();
        $this->assertEvents($response, ['medication_inventory.delete'], $id, $admin->id);
        $this->assertDatabaseMissing('older_adult_medications', ['id' => $id]);
    }

    public function test_rejected_user_changes_do_not_claim_success(): void
    {
        $this->admin();
        $target = User::factory()->approvedFamily()->create();
        $this->putJson('/api/admin/users/'.$target->id, [
            'name' => 'Intento', 'email' => $target->email, 'role' => 'invalid', 'is_approved' => true,
        ])->assertUnprocessable();

        $this->assertSame([], $this->events);
        $this->assertSame('familiar', $target->fresh()->role);
    }

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function assertEvents(TestResponse $response, array $names, int $target, ?int $actor): void
    {
        $this->assertSame($names, array_column($this->events, 'event'));
        foreach ($this->events as $event) {
            $this->assertSame('success', $event['result']);
            $this->assertSame($target, $event['resource_id']);
            $this->assertSame($actor, $event['actor_id']);
            $this->assertSame($response->headers->get('X-Correlation-ID'), $event['correlation_id']);
            $this->assertNotEmpty($event['correlation_id']);
        }
    }
}

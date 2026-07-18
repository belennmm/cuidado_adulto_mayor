<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_rejected_by_sanctum_on_admin_routes(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_non_admin_is_rejected_by_admin_middleware(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'familiar', 'is_approved' => true]));

        $this->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede realizar esta accion.');
    }

    public function test_admin_can_create_show_update_approve_and_delete_users(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));
        $email = fake()->unique()->safeEmail();

        $created = $this->postJson('/api/admin/users', [
            'name' => 'Cuidadora de prueba',
            'email' => $email,
            'password' => 'password123',
            'role' => 'cuidador_familiar',
        ])->assertCreated()->assertJsonPath('user.role', 'familiar')->json('user');

        $userId = $created['id'];
        $this->getJson("/api/admin/users/{$userId}")
            ->assertOk()->assertJsonPath('user.email', $email);

        $this->putJson("/api/admin/users/{$userId}", [
            'name' => 'Nombre actualizado',
            'email' => $email,
            'role' => 'profesional',
            'is_approved' => false,
        ])->assertOk()->assertJsonPath('user.name', 'Nombre actualizado');

        $this->patchJson("/api/admin/users/{$userId}/approve")
            ->assertOk()->assertJsonPath('user.is_approved', true);

        $this->deleteJson("/api/admin/users/{$userId}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_user_creation_validates_payload(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));

        $this->postJson('/api/admin/users', [
            'name' => '', 'email' => 'not-an-email', 'password' => 'short', 'role' => 'unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_only_pending_requests_can_be_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));
        $pending = User::factory()->create(['is_approved' => false]);
        $approved = User::factory()->create(['is_approved' => true]);

        $this->deleteJson("/api/admin/users/{$pending->id}/reject")->assertOk();
        $this->assertModelMissing($pending);

        $this->deleteJson("/api/admin/users/{$approved->id}/reject")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Solo se pueden rechazar solicitudes pendientes.');
        $this->assertModelExists($approved);
    }
}

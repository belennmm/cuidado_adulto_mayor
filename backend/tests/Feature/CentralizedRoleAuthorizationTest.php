<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentralizedRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_family_alias_can_access_family_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'cuidador_familiar',
            'is_approved' => true,
        ]));

        $this->getJson('/api/family/overview')->assertOk();
    }

    public function test_approved_professional_alias_can_access_professional_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'cuidador_profesional',
            'is_approved' => true,
        ]));

        $this->getJson('/api/professional/overview')->assertOk();
    }

    public function test_wrong_role_and_pending_accounts_are_rejected_before_controller(): void
    {
        Sanctum::actingAs(User::factory()->approvedFamily()->create());
        $this->getJson('/api/professional/overview')->assertForbidden();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]));
        $this->getJson('/api/professional/overview')
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.');
    }

    public function test_legacy_administrator_alias_can_access_admin_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'administrador',
            'is_approved' => true,
        ]));

        $this->getJson('/api/admin/users')->assertOk();
    }

    public function test_administrator_alias_uses_real_tokens_and_loses_access_when_unapproved(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'password' => 'Alias-Secure!123', 'is_approved' => true]);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Alias-Secure!123'])
            ->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
        $user->forceFill(['is_approved' => false])->save();
        $this->withToken($token)->getJson('/api/admin/users')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unapproved_administrator_alias_cannot_login(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'password' => 'Alias-Secure!123', 'is_approved' => false]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Alias-Secure!123'])->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

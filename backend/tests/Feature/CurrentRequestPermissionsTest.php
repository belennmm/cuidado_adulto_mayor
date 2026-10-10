<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentRequestPermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_permissions_use_current_database_state_even_when_authentication_is_cached(string $role, string $path): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $headers = $this->headersFor($user);
        $this->getJson($path, $headers)->assertOk();

        // Simulate an external state change that does not dispatch model observers.
        DB::table('users')->where('id', $user->id)->update(['is_approved' => false]);
        $this->assertSame(1, $user->tokens()->count());
        $this->getJson($path, [...$headers, 'X-Role' => $role, 'X-Is-Approved' => 'true'])->assertUnauthorized();
        $this->putJson('/api/me', ['name' => 'Intento', 'email' => $user->email, 'is_approved' => true], $headers)->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('users', ['id' => $user->id, 'name' => 'Intento']);
    }

    public static function roles(): array
    {
        return [
            ['admin', '/api/admin/users'], ['profesional', '/api/professional/overview'],
            ['familiar', '/api/family/overview'], ['cuidador_profesional', '/api/professional/overview'],
            ['cuidador_familiar', '/api/family/overview'],
        ];
    }

    public function test_role_changes_and_unknown_roles_do_not_keep_cached_administrative_access(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $headers = $this->headersFor($user);
        $this->getJson('/api/admin/users', $headers)->assertOk();
        foreach (['familiar', 'auditor', ''] as $role) {
            DB::table('users')->where('id', $user->id)->update(['role' => $role]);
            $this->getJson('/api/admin/users', $headers)->assertUnauthorized();
            $this->putJson('/api/admin/users/999999', ['role' => 'admin', 'is_approved' => true], $headers)->assertUnauthorized();
        }
    }

    public function test_assignment_is_rechecked_between_requests_with_the_same_token(): void
    {
        $first = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $second = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $adult = OlderAdult::create(['full_name' => 'Asignado', 'created_by' => $first->id, 'professional_caregiver_id' => $first->id]);
        $headers = $this->headersFor($first);
        $path = '/api/professional/older-adults/'.$adult->id;
        $this->getJson($path, $headers)->assertOk();

        DB::table('older_adults')->where('id', $adult->id)->update(['professional_caregiver_id' => $second->id]);
        $this->getJson($path, $headers)->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
        $this->getJson('/api/professional/older-adults', $headers)->assertJsonCount(0, 'older_adults');
        $this->getJson($path, $this->headersFor($second))->assertOk();
    }

    public function test_revoked_expired_and_deleted_credentials_are_rechecked_without_resetting_the_guard(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $headers = $this->headersFor($user);
        $this->getJson('/api/me', $headers)->assertOk();
        $user->tokens()->delete();
        $this->getJson('/api/me', $headers)->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $headers = $this->headersFor($user);
        $this->getJson('/api/me', $headers)->assertOk();
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/api/me', $headers)->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $headers = $this->headersFor($user);
        $this->getJson('/api/me', $headers)->assertOk();
        DB::table('users')->where('id', $user->id)->delete();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
    }

    public function test_a_changed_bearer_token_never_reuses_the_previous_identity(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $family = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $this->getJson('/api/admin/users', $this->headersFor($admin))->assertOk();
        $headers = $this->headersFor($family);
        $this->getJson('/api/me', $headers)->assertOk()->assertJsonPath('user.id', $family->id);
        $this->getJson('/api/admin/users', $headers)->assertForbidden();
        $this->getJson('/api/me', ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
    }

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('current-request')->plainTextToken];
    }
}

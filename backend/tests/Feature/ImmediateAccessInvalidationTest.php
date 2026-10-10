<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountSecurityState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImmediateAccessInvalidationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('securityChanges')]
    public function test_external_security_changes_invalidate_all_old_tokens_at_the_next_request(string $role, array $changes): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $other = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $headers = $this->headersFor($user);
        $second = $this->headersFor($user);
        $otherHeaders = $this->headersFor($other);
        $this->seedCredentials($user);
        $this->getJson('/api/me', $headers)->assertOk();
        DB::table('users')->where('id', $user->id)->update($changes);

        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->getJson('/api/incidents', $second)->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->getJson('/api/me', $otherHeaders)->assertOk()->assertJsonPath('user.id', $other->id);

        DB::table('users')->where('id', $user->id)->update(['role' => $role, 'is_approved' => true]);
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->getJson('/api/me', $this->headersFor($user->fresh()))->assertOk();
    }

    public static function securityChanges(): array
    {
        return [
            ['admin', ['role' => 'familiar']], ['profesional', ['role' => 'admin']],
            ['familiar', ['role' => 'profesional']], ['cuidador_profesional', ['role' => 'familiar']],
            ['cuidador_familiar', ['role' => 'admin']], ['admin', ['is_approved' => false]],
            ['profesional', ['is_approved' => false]], ['familiar', ['is_approved' => false]],
            ['familiar', ['role' => 'auditor']],
        ];
    }

    public function test_old_credentials_cannot_revoke_tokens_issued_after_an_external_role_change(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $old = $this->headersFor($user);
        DB::table('users')->where('id', $user->id)->update(['role' => 'profesional']);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $new = ['Authorization' => 'Bearer '.$response->json('token')];
        $this->getJson('/api/professional/overview', $new)->assertOk();
        $this->getJson('/api/me', $old)->assertUnauthorized();
        $this->getJson('/api/professional/overview', $new)->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_external_password_changes_invalidate_previous_credentials(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $headers = $this->headersFor($user);
        DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('Changed-Password!123')]);
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Changed-Password!123'])->assertOk();
    }

    public function test_legacy_credentials_without_an_issuance_fingerprint_require_a_new_login(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $headers = $this->headersFor($user);
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->update(['security_fingerprint' => null]);
        $this->getJson('/api/admin/users', $headers)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_ordinary_profile_changes_do_not_invalidate_tokens_or_expose_the_fingerprint(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $token = $user->createToken('state');
        $this->assertSame(AccountSecurityState::fingerprint($user), $token->accessToken->security_fingerprint);
        $this->assertArrayNotHasKey('security_fingerprint', $token->accessToken->toArray());
        DB::table('users')->where('id', $user->id)->update(['name' => 'Cambio permitido']);
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token->plainTextToken])
            ->assertOk()->assertJsonPath('user.name', 'Cambio permitido');
    }

    public function test_pending_accounts_cannot_keep_even_manually_issued_tokens(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => false]);
        $headers = $this->headersFor($user);
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('invalidation')->plainTextToken];
    }

    private function seedCredentials(User $user): void
    {
        DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset', 'created_at' => now()]);
    }
}

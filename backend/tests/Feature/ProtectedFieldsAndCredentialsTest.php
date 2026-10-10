<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use App\Services\AdminUserService;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProtectedFieldsAndCredentialsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_profile_payload_cannot_modify_security_fields_for_any_role(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $originalPassword = $user->password;
        $headers = $this->headersFor($user);

        $this->putJson('/api/me', [
            'name' => 'Nombre permitido', 'email' => $user->email,
            'id' => 999999, 'role' => $role === 'admin' ? 'profesional' : 'admin',
            'is_approved' => false, 'password' => 'Password-Inyectada!999',
            'remember_token' => 'inyectado', 'privacy_consent_at' => '2000-01-01',
            'privacy_policy_version' => 'inyectada', 'created_at' => '2000-01-01',
            'user_id' => 999999, 'professional_caregiver_id' => 999999,
        ], $headers)->assertOk()->assertJsonPath('user.role', $role)->assertJsonPath('user.is_approved', true);

        $user->refresh();
        $this->assertSame('Nombre permitido', $user->name);
        $this->assertSame($role, $user->role);
        $this->assertTrue($user->is_approved);
        $this->assertSame($originalPassword, $user->password);
        $this->assertNull($user->privacy_consent_at);
        $this->assertNull($user->privacy_policy_version);
        $this->assertNull($user->remember_token);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public static function roles(): array
    {
        return [['admin'], ['profesional'], ['familiar'], ['cuidador_profesional'], ['cuidador_familiar']];
    }

    public function test_profile_service_itself_filters_protected_fields_and_updates_only_owned_adults_names(): void
    {
        $user = User::factory()->create(['name' => 'Nombre compartido', 'role' => 'familiar', 'is_approved' => true]);
        $other = User::factory()->create(['name' => $user->name, 'role' => 'familiar', 'is_approved' => true]);
        $ownAdult = OlderAdult::create(['full_name' => 'Propio', 'family_caregiver_id' => $user->id, 'caregiver_family' => $user->name]);
        $otherAdult = OlderAdult::create(['full_name' => 'Ajeno', 'family_caregiver_id' => $other->id, 'caregiver_family' => $user->name]);
        $legacyAdult = OlderAdult::create(['full_name' => 'Sin asignacion', 'caregiver_family' => $user->name]);
        $oldHash = $user->password;

        app(AuthService::class)->updateProfile($user, [
            'name' => 'Nombre nuevo', 'email' => $user->email, 'role' => 'admin',
            'is_approved' => false, 'password' => 'Password-Inyectada!999', 'privacy_policy_version' => 'inyectada',
        ]);

        $this->assertSame('familiar', $user->refresh()->role);
        $this->assertTrue($user->is_approved);
        $this->assertSame($oldHash, $user->password);
        $this->assertNull($user->privacy_policy_version);
        $this->assertSame('Nombre nuevo', $ownAdult->refresh()->caregiver_family);
        $this->assertSame('Nombre compartido', $otherAdult->refresh()->caregiver_family);
        $this->assertSame('Nombre compartido', $legacyAdult->refresh()->caregiver_family);
    }

    public function test_public_registration_cannot_set_approval_or_administrative_fields(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Registro seguro', 'email' => 'registro-seguro@example.com',
            'password' => 'Secure-Test!123', 'role' => 'familiar', 'privacy_consent' => true,
            'is_approved' => true, 'id' => 999999, 'privacy_policy_version' => 'inyectada',
            'privacy_consent_at' => '2000-01-01', 'remember_token' => 'inyectado',
        ])->assertCreated()->assertJsonPath('user.role', 'familiar')->assertJsonPath('user.is_approved', false);

        $user = User::query()->sole();
        $this->assertNotEquals(999999, $user->id);
        $this->assertSame(config('privacy.policy_version'), $user->privacy_policy_version);
        $this->assertTrue($user->privacy_consent_at->isToday());
        $this->assertNull($user->remember_token);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('securityChanges')]
    public function test_admin_security_changes_revoke_every_target_credential(string $oldRole, string $newRole, bool $approved): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $target = User::factory()->create(['role' => $oldRole, 'is_approved' => true]);
        $oldTokens = [$this->headersFor($target), $this->headersFor($target)];
        $this->getJson('/api/me', $oldTokens[0])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->seedCredentials($target);
        $adminHeaders = $this->headersFor($admin);

        $this->putJson("/api/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'role' => $newRole, 'is_approved' => $approved,
        ], $adminHeaders)->assertOk();

        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        foreach ($oldTokens as $oldHeaders) {
            $this->app['auth']->forgetGuards();
            $this->getJson('/api/me', $oldHeaders)->assertUnauthorized();
            $this->getJson('/api/incidents', $oldHeaders)->assertUnauthorized();
        }

        if (! $approved) {
            $this->app['auth']->forgetGuards();
            $this->postJson('/api/login', ['email' => $target->email, 'password' => 'password'])->assertForbidden();
            $this->app['auth']->forgetGuards();
            $this->patchJson("/api/admin/users/{$target->id}/approve", [], $adminHeaders)->assertOk();
            $this->app['auth']->forgetGuards();
            $this->getJson('/api/me', $oldTokens[0])->assertUnauthorized();
        }
    }

    public static function securityChanges(): array
    {
        return [
            'professional to family' => ['profesional', 'familiar', true],
            'professional to admin' => ['profesional', 'admin', true],
            'family to professional' => ['familiar', 'profesional', true],
            'admin to family' => ['admin', 'familiar', true],
            'disapprove professional' => ['profesional', 'profesional', false],
            'disapprove family' => ['familiar', 'familiar', false],
            'disapprove admin' => ['admin', 'admin', false],
        ];
    }

    public function test_ordinary_admin_edit_preserves_tokens_and_filters_unapproved_input_fields(): void
    {
        $target = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $headers = $this->headersFor($target);
        app(AdminUserService::class)->update($target, [
            'name' => 'Nombre actualizado', 'email' => $target->email, 'role' => 'profesional', 'is_approved' => true,
            'privacy_consent_at' => '2000-01-01', 'privacy_policy_version' => 'inyectada',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNull($target->refresh()->privacy_consent_at);
        $this->assertNull($target->privacy_policy_version);
        $this->getJson('/api/me', $headers)->assertOk()->assertJsonPath('user.name', 'Nombre actualizado');
    }

    public function test_model_security_changes_and_deletion_revoke_credentials_outside_admin_controller(): void
    {
        foreach ([['role' => 'familiar'], ['is_approved' => false]] as $change) {
            $user = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
            $headers = $this->headersFor($user);
            $user->update($change);
            $this->app['auth']->forgetGuards();
            $this->getJson('/api/me', $headers)->assertUnauthorized();
        }
        $deleted = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $headers = $this->headersFor($deleted);
        $this->seedCredentials($deleted);
        $deleted->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->assertDatabaseMissing('sessions', ['user_id' => $deleted->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $deleted->email]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_security_change_and_revocation_are_rolled_back_together(): void
    {
        $target = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $this->headersFor($target);
        $this->seedCredentials($target);

        try {
            DB::transaction(function () use ($target) {
                app(AdminUserService::class)->update($target, [
                    'name' => $target->name, 'email' => $target->email, 'role' => 'familiar', 'is_approved' => true,
                ]);
                throw new \RuntimeException('Simular fallo posterior');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simular fallo posterior', $exception->getMessage());
        }

        $this->assertSame('profesional', $target->refresh()->role);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertDatabaseHas('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_password_change_revokes_credentials_for_both_previous_and_new_email(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $this->seedCredentials($user);
        $oldEmail = $user->email;
        $oldHeaders = $this->headersFor($user);

        $this->putJson('/api/me', [
            'name' => $user->name, 'email' => 'correo-nuevo@example.com', 'current_password' => 'password',
            'new_password' => 'New-Password!123', 'new_password_confirmation' => 'New-Password!123',
        ], $oldHeaders)->assertOk();

        $this->assertTrue(Hash::check('New-Password!123', $user->refresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', $oldHeaders)->assertUnauthorized();
    }

    private function headersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test-browser')->plainTextToken];
    }

    private function seedCredentials(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => base64_encode('test'), 'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset-test', 'created_at' => now()]);
    }
}

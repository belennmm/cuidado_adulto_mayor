<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use App\Services\CaregiverScheduleService;
use App\Services\MobilityExerciseService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\SecurityScanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeastPrivilegeTokensTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_login_issues_only_the_scopes_for_the_approved_role(string $role, string $module, string $path): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'abilities' => ['*', 'admin:write']])
            ->assertUnprocessable()->assertJsonValidationErrors('abilities');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $plain = $response->json('token');
        $token = PersonalAccessToken::findToken($plain);
        $this->assertSame(['account:read', 'account:write', 'care:read', 'care:write', $module.':read', $module.':write'], $token->abilities);
        $this->assertNotContains('*', $token->abilities);
        $this->assertNotNull($token->expires_at);
        $headers = ['Authorization' => 'Bearer '.$plain];
        $this->getJson('/api/me', $headers)->assertOk();
        $this->getJson($path, $headers)->assertOk();
        $this->postJson('/api/logout', [], $headers)->assertOk();
    }

    public static function roles(): array
    {
        return [
            ['admin', 'admin', '/api/admin/users'], ['profesional', 'professional', '/api/professional/overview'],
            ['familiar', 'family', '/api/family/overview'], ['cuidador_profesional', 'professional', '/api/professional/overview'],
            ['cuidador_familiar', 'family', '/api/family/overview'],
        ];
    }

    public function test_read_only_credentials_cannot_write_even_when_the_role_allows_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $token = $admin->createToken('read-only', ['account:read', 'admin:read'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/admin/users', $headers)->assertOk();
        $this->getJson('/api/me', $headers)->assertOk();
        $this->postJson('/api/admin/older-adults', ['full_name' => 'Indebido'], $headers)->assertForbidden();
        $this->deleteJson('/api/admin/users/'.$admin->id, [], $headers)->assertForbidden();
        $this->putJson('/api/me', ['name' => 'Indebido', 'email' => $admin->email], $headers)->assertForbidden();
        $this->assertDatabaseCount('older_adults', 0);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => $admin->name]);
    }

    public function test_account_only_and_empty_scopes_cannot_access_care_or_discover_identifiers(): void
    {
        $user = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        foreach ([['account:read'], []] as $abilities) {
            $headers = ['Authorization' => 'Bearer '.$user->createToken('restricted', $abilities)->plainTextToken];
            $this->getJson('/api/professional/overview', $headers)->assertForbidden();
            $this->getJson('/api/professional/older-adults/999999', $headers)->assertForbidden();
            $this->postJson('/api/schedules', ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '16:00'], $headers)->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
        $this->assertDatabaseCount('caregiver_schedules', 0);
    }

    public function test_forged_administrative_scopes_never_override_the_current_role(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        foreach ([['admin:read', 'admin:write'], ['*']] as $abilities) {
            $headers = ['Authorization' => 'Bearer '.$user->createToken('forged', $abilities)->plainTextToken];
            $this->getJson('/api/admin/users', $headers)->assertForbidden();
            $this->patchJson('/api/admin/users/'.$user->id.'/approve', [], $headers)->assertForbidden();
        }
    }

    public function test_an_external_promotion_does_not_expand_an_existing_scoped_token(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $plain = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('token');
        DB::table('users')->where('id', $user->id)->update(['role' => 'admin']);
        $headers = ['Authorization' => 'Bearer '.$plain];
        $this->getJson('/api/admin/users', $headers)->assertUnauthorized();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
    }

    #[DataProvider('unprivilegedServiceUsers')]
    public function test_mobility_service_denies_writes_without_an_approved_admin(string $role, bool $approved): void
    {
        $actor = User::factory()->create(['role' => $role, 'is_approved' => $approved]);
        try {
            app(MobilityExerciseService::class)->create([], $actor);
            $this->fail('An unauthorized service call must not create an exercise.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('mobility_exercises', 0);
        }
    }

    public static function unprivilegedServiceUsers(): array
    {
        return [['familiar', true], ['profesional', true], ['super_admin', true], ['administrador', false], ['admin', false]];
    }

    public function test_seeders_cannot_install_known_privileged_accounts_in_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            foreach ([new InitialDataSeeder, new SecurityScanSeeder] as $seeder) {
                try {
                    $seeder->run();
                    $this->fail('Production must not accept demonstration or scan accounts.');
                } catch (\LogicException) {
                    $this->assertDatabaseCount('users', 0);
                }
            }
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_scope_reductions_are_applied_even_with_a_cached_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $token = $user->createToken('reduced', ['professional:read', 'account:read']);
        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];
        $this->getJson('/api/professional/overview', $headers)->assertOk();
        DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->update(['abilities' => json_encode(['account:read'])]);
        $this->getJson('/api/professional/overview', $headers)->assertForbidden();
        $this->getJson('/api/me', $headers)->assertOk();
    }

    public function test_schedule_service_requires_approved_administrators_and_current_ownership(): void
    {
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $schedule = CaregiverSchedule::create(['user_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '16:00']);
        $actors = [
            User::factory()->create(['role' => 'admin', 'is_approved' => false]),
            User::factory()->create(['role' => 'super_admin', 'is_approved' => true]),
            User::factory()->create(['role' => 'profesional', 'is_approved' => true]),
        ];
        foreach ($actors as $actor) {
            try {
                app(CaregiverScheduleService::class)->update($actor, $schedule, ['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '18:00']);
                $this->fail('A direct service call must obey the schedule policy.');
            } catch (AuthorizationException) {
                $this->assertDatabaseHas('caregiver_schedules', ['id' => $schedule->id, 'start_time' => '08:00']);
            }
        }
    }
}

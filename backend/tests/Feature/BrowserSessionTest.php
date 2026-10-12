<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CredentialRevocationService;
use App\Support\AccountSecurityState;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrowserSessionTest extends TestCase
{
    use RefreshDatabase;

    private array $jar = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database', 'session.secure' => true]);
        $this->withCredentials();
    }

    private function browser(string $method, string $uri, array $data = [], bool $csrf = true, array $headers = []): TestResponse
    {
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        $this->defaultCookies = [];
        $this->unencryptedCookies = $this->jar;
        $headers = array_merge(['X-Cuidado-Session' => '1'], $headers);
        if ($csrf && isset($this->jar['XSRF-TOKEN'])) {
            $headers['X-XSRF-TOKEN'] = $this->jar['XSRF-TOKEN'];
        }
        $response = $this->json($method, $uri, $data, $headers);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->jar[$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    }

    private function login(string $role = 'familiar'): User
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('token');

        return $user;
    }

    public function test_browser_login_uses_http_only_secure_cookie_without_issuing_a_bearer_token(): void
    {
        $response = $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        $cookie = $cookies[config('session.cookie')];
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertFalse($cookies['XSRF-TOKEN']->isHttpOnly());
        $user = $this->login();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_rotates_session_and_csrf_and_rejects_the_previous_cookie(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $anonymousJar = $this->jar;
        $oldId = app('session')->driver()->getId();
        $oldCsrf = app('session')->driver()->token();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertNotSame($oldId, app('session')->driver()->getId());
        $this->assertNotSame($oldCsrf, app('session')->driver()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $oldId]);
        $authenticatedJar = $this->jar;
        $this->jar = $anonymousJar;
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->jar = $authenticatedJar;
        $this->browser('GET', '/api/me')->assertOk();
    }

    public function test_missing_invalid_and_cross_origin_csrf_are_rejected_without_login(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $data = ['email' => $user->email, 'password' => 'password'];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', $data, false)->assertStatus(419);
        $this->browser('POST', '/api/login', $data, false, ['X-XSRF-TOKEN' => 'forged'])->assertStatus(419);
        $this->browser('POST', '/api/login', $data, false, ['Sec-Fetch-Site' => 'same-origin'])->assertStatus(419);
        $this->browser('POST', '/api/login', $data, true, ['Origin' => 'https://evil.test'])->assertStatus(419);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_authenticated_mutations_and_logout_require_csrf_and_logout_revokes_cookie(): void
    {
        $user = $this->login();
        $this->browser('PUT', '/api/me', ['name' => 'Changed'], false)->assertStatus(419);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->browser('POST', '/api/logout', [], false)->assertStatus(419);
        $captured = $this->jar;
        $id = app('session')->driver()->getId();
        $this->browser('POST', '/api/logout')->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => $id]);
        $this->jar = $captured;
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_idle_timeout_expires_at_the_boundary(): void
    {
        $this->login();
        $this->travel(15)->minutes();
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_activity_extends_idle_deadline_but_never_absolute_expiration(): void
    {
        $this->login();
        for ($step = 0; $step < 5; $step++) {
            $this->travel(10)->minutes();
            $this->browser('GET', '/api/me')->assertOk();
        }
        $this->travel(10)->minutes();
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_account_changes_invalidate_browser_access_on_the_next_request(): void
    {
        $user = $this->login();
        User::query()->whereKey($user->id)->update(['is_approved' => false]);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_browser_cannot_fall_back_to_bearer_authentication(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('api')->plainTextToken;
        $this->browser('GET', '/api/me', [], true, ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    #[DataProvider('roles')]
    public function test_cookie_authentication_keeps_role_permissions(string $role, string $allowed, string $forbidden): void
    {
        $this->login($role);
        $this->browser('GET', $allowed)->assertOk();
        $this->browser('GET', $forbidden)->assertForbidden();
    }

    public static function roles(): array
    {
        return [
            ['admin', '/api/admin/users', '/api/family/overview'],
            ['profesional', '/api/professional/overview', '/api/admin/users'],
            ['familiar', '/api/family/overview', '/api/admin/users'],
            ['cuidador_profesional', '/api/professional/overview', '/api/admin/users'],
            ['cuidador_familiar', '/api/family/overview', '/api/admin/users'],
        ];
    }

    public function test_password_change_does_not_restore_a_revoked_session_at_the_end_of_the_request(): void
    {
        $user = $this->login();
        $captured = $this->jar;
        $this->browser('PUT', '/api/me', [
            'name' => $user->name, 'email' => $user->email, 'current_password' => 'password',
            'new_password' => 'New-Secure!1234', 'new_password_confirmation' => 'New-Secure!1234',
        ])->assertOk();
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->jar = $captured;
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_credentialed_cors_allows_only_configured_origins(): void
    {
        $this->browser('GET', '/api/csrf-cookie', [], true, ['Origin' => 'http://localhost:3000'])
            ->assertOk()->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->browser('OPTIONS', '/api/login', [], false, [
            'Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'X-XSRF-TOKEN,X-Cuidado-Session',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->browser('GET', '/api/csrf-cookie', [], true, ['Origin' => 'https://evil.test'])
            ->assertHeaderMissing('Access-Control-Allow-Origin')->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_logout_all_requires_csrf_and_password_and_revokes_only_the_current_account(): void
    {
        $user = $this->login();
        $jarA = $this->jar;
        $tokenA = $user->createToken('device-a')->accessToken;
        $tokenB = $user->createToken('device-b')->accessToken;
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $jarB = $this->jar;
        $this->jar = [];
        $other = $this->login();
        $otherJar = $this->jar;
        $otherToken = $other->createToken('other-device')->accessToken;
        $this->jar = $jarA;
        $this->browser('POST', '/api/logout-all', ['current_password' => 'password'], false)->assertStatus(419);
        $this->browser('POST', '/api/logout-all', ['current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->browser('POST', '/api/logout-all', ['current_password' => 'password', 'user_id' => $other->id])->assertUnprocessable();
        $this->browser('GET', '/api/me')->assertOk();
        $this->browser('POST', '/api/logout-all', ['current_password' => 'password'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenA->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenB->id]);
        $this->assertSame(1, $user->fresh()->session_version);
        foreach ([$jarA, $jarB, $this->jar] as $jar) {
            $this->jar = $jar;
            $this->browser('GET', '/api/me')->assertUnauthorized();
        }
        $this->jar = $otherJar;
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $other->id);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);
    }

    public function test_normal_logout_keeps_another_device_session_active(): void
    {
        $user = $this->login();
        $jarA = $this->jar;
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $jarB = $this->jar;
        $this->jar = $jarA;
        $this->browser('POST', '/api/logout')->assertOk();
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->jar = $jarB;
        $this->browser('GET', '/api/me')->assertOk();
    }

    public function test_security_version_invalidates_sessions_even_when_privileges_are_restored(): void
    {
        $user = $this->login();
        $originalFingerprint = AccountSecurityState::fingerprint($user->fresh());
        $user->forceFill(['role' => 'profesional'])->save();
        $user->forceFill(['role' => 'familiar'])->save();
        $this->assertSame(2, $user->fresh()->session_version);
        $this->assertNotSame($originalFingerprint, AccountSecurityState::fingerprint($user->fresh()));
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->browser('GET', '/api/me')->assertOk();
    }

    public function test_logout_all_revokes_file_sessions_without_relying_on_the_database_session_table(): void
    {
        $directory = storage_path('framework/testing-sessions-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($directory);
        config(['session.driver' => 'file', 'session.files' => $directory]);
        try {
            $this->login();
            $captured = $this->jar;
            $this->assertDatabaseCount('sessions', 0);
            $this->browser('POST', '/api/logout-all', ['current_password' => 'password'])->assertOk();
            $this->jar = $captured;
            $this->browser('GET', '/api/me')->assertUnauthorized();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_saving_a_stale_model_does_not_undo_a_later_revocation(): void
    {
        $user = $this->login();
        app(CredentialRevocationService::class)->revoke($user);
        app(CredentialRevocationService::class)->revoke($user->fresh());
        $user->forceFill(['name' => 'Updated name'])->save();
        $this->assertSame(2, $user->fresh()->session_version);
    }

    public function test_anonymous_recovery_and_reset_require_csrf(): void
    {
        Queue::fake();
        $this->browser('POST', '/api/forgot-password', ['email' => 'anonymous@example.test'], false)->assertStatus(419);
        $this->browser('POST', '/api/reset-password', [], false)->assertStatus(419);
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/forgot-password', ['email' => 'anonymous@example.test'])->assertStatus(202);
    }

    public function test_recovery_reset_revokes_existing_browser_and_bearer_credentials(): void
    {
        $user = $this->login();
        $oldJar = $this->jar;
        $bearer = $user->createToken('before-reset')->plainTextToken;
        $token = Password::broker()->createToken($user);
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'Browser-Recovery!2026', 'password_confirmation' => 'Browser-Recovery!2026',
        ])->assertOk();
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->jar = $oldJar;
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        Auth::forgetGuards();
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$bearer])->assertUnauthorized();
    }

    public function test_an_old_api_token_cannot_revoke_a_new_browser_session_after_external_role_change(): void
    {
        $user = $this->login();
        $oldToken = $user->createToken('old')->plainTextToken;
        DB::table('users')->where('id', $user->id)->update(['role' => 'profesional']);
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
        Auth::forgetGuards();
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$oldToken])->assertUnauthorized();
        $this->browser('GET', '/api/me')->assertOk();
    }

    public function test_mixed_sessions_are_capped_and_eviction_rejects_a_captured_cookie(): void
    {
        config(['session.max_per_user' => 2]);
        $user = $this->login();
        $old = $this->jar;
        $this->travel(1)->seconds();
        $user->createToken('api-device');
        $this->travel(1)->seconds();
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertDatabaseCount('account_sessions', 2);
        $this->browser('GET', '/api/sessions')->assertOk()->assertJsonCount(2, 'sessions');
        $this->jar = $old;
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_listing_records_metadata_and_closes_only_the_selected_device(): void
    {
        $user = $this->login();
        $first = $this->jar;
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'], true, ['User-Agent' => 'Phone browser'])->assertOk();
        $rows = $this->browser('GET', '/api/sessions', [], true, ['User-Agent' => 'Phone browser'])->assertOk()->json('sessions');
        $this->assertCount(2, $rows);
        $other = collect($rows)->firstWhere('current', false);
        $current = collect($rows)->firstWhere('current', true);
        $this->assertSame('Phone browser', $current['device']);
        $this->assertSame('127.0.0.1', $current['ip_address']);
        $this->assertArrayNotHasKey('browser_hash', $current);
        $this->browser('DELETE', '/api/sessions/'.$other['id'], [], false)->assertStatus(419);
        $this->browser('DELETE', '/api/sessions/'.$other['id'])->assertOk()->assertJsonPath('current', false);
        $this->browser('GET', '/api/me')->assertOk();
        $this->jar = $first;
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_recent_authentication_expires_even_while_the_session_is_active(): void
    {
        $user = $this->login();
        $payload = ['name' => 'Updated', 'email' => $user->email];
        $this->browser('PUT', '/api/me', $payload)->assertOk();
        $this->travel(5)->minutes();
        $this->browser('GET', '/api/me')->assertOk();
        $this->browser('PUT', '/api/me', $payload)->assertStatus(428)->assertJsonPath('code', 'reauthentication_required');
        $this->browser('POST', '/api/reauthenticate', ['current_password' => 'wrong'])->assertUnprocessable();
        $this->browser('PUT', '/api/me', $payload)->assertStatus(428);
        $this->browser('POST', '/api/reauthenticate', ['current_password' => 'password'], false)->assertStatus(419);
        $this->browser('POST', '/api/reauthenticate', ['current_password' => 'password'])->assertOk();
        $this->browser('PUT', '/api/me', $payload)->assertOk();
    }

    public function test_closing_the_current_browser_session_logs_it_out(): void
    {
        $this->login();
        $row = collect($this->browser('GET', '/api/sessions')->assertOk()->json('sessions'))->firstWhere('current', true);
        $this->browser('DELETE', '/api/sessions/'.$row['id'])->assertOk()->assertJsonPath('current', true);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_password_reset_revokes_file_sessions_and_keeps_other_accounts_active(): void
    {
        $directory = storage_path('framework/testing-sessions-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($directory);
        config(['session.driver' => 'file', 'session.files' => $directory]);
        try {
            $user = $this->login();
            $old = $this->jar;
            $this->jar = [];
            $this->login();
            $other = $this->jar;
            $token = Password::broker()->createToken($user);
            $this->jar = [];
            $this->browser('GET', '/api/csrf-cookie')->assertOk();
            $this->browser('POST', '/api/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'File-Recovery!2026', 'password_confirmation' => 'File-Recovery!2026'])->assertOk();
            $this->jar = $old;
            $this->browser('GET', '/api/me')->assertUnauthorized();
            $this->jar = $other;
            $this->browser('GET', '/api/me')->assertOk();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_mfa_browser_challenge_requires_csrf_same_cookie_and_second_factor_before_session(): void
    {
        $secret = Totp::secret();
        $user = User::factory()->approvedProfessional()->create();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled_at' => now()])->save();
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $initial = $this->jar;
        $challenge = $this->browser('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(202)->assertJsonMissingPath('user')->json('challenge');
        $bound = $this->jar;
        $code = Totp::code($secret, intdiv(now()->timestamp, 30));
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->browser('POST', '/api/mfa/verify', compact('challenge', 'code'), false)->assertStatus(419);
        $this->jar = [];
        $this->browser('GET', '/api/csrf-cookie')->assertOk();
        $this->browser('POST', '/api/mfa/verify', compact('challenge', 'code'))->assertUnprocessable();
        $this->jar = $bound;
        $this->browser('POST', '/api/mfa/verify', compact('challenge', 'code'))->assertOk()->assertJsonMissingPath('token');
        $authenticated = $this->jar;
        $this->assertNotSame($bound[config('session.cookie')], $authenticated[config('session.cookie')]);
        $this->browser('GET', '/api/me')->assertOk();
        $this->jar = $initial;
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->jar = $authenticated;
        DB::table('users')->where('id', $user->id)->update(['is_approved' => false]);
        $this->browser('POST', '/api/reauthenticate', ['current_password' => 'password'])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

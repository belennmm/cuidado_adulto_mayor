<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
}

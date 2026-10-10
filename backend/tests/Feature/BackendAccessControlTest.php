<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncidentListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackendAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_API = ['api/ping', 'api/login', 'api/register'];

    private const ACCOUNT_API = ['api/me', 'api/logout'];

    public function test_every_private_api_route_requires_authentication(): void
    {
        $routes = $this->privateApiRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $route->uri());
            $this->json($route->methods()[0], $this->resourceUrl($route->uri()))->assertUnauthorized();
        }
    }

    public function test_invalid_token_cannot_access_any_private_api_route(): void
    {
        foreach ($this->privateApiRoutes() as $route) {
            $this->json($route->methods()[0], $this->resourceUrl($route->uri()), [], [
                'Authorization' => 'Bearer invalid-token',
            ])->assertUnauthorized();
        }
    }

    public function test_public_endpoints_remain_accessible_without_authentication(): void
    {
        $this->getJson('/api/ping')->assertOk()->assertExactJson(['ok' => true]);
        $this->getJson('/')->assertOk();
        $this->getJson('/up')->assertOk();
        $this->postJson('/api/login', [])->assertUnprocessable();
        $this->postJson('/api/register', [])->assertUnprocessable();
    }

    public function test_real_token_authenticates_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $token = $user->createToken('access-test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/me', $headers)->assertOk()->assertJsonPath('user.id', $user->id);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/logout', [], $headers)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create(['is_approved' => true]);
        $token = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    #[DataProvider('restrictedUsers')]
    public function test_unknown_roles_and_pending_caregivers_cannot_access_care_resources(string $role, bool $approved): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_approved' => $approved]));

        foreach ($this->privateApiRoutes() as $route) {
            if (in_array($route->uri(), self::ACCOUNT_API, true)) {
                continue;
            }

            // Missing resource IDs must not bypass the role check or disclose existence.
            $this->json($route->methods()[0], $this->resourceUrl($route->uri()))->assertForbidden();
        }
    }

    public static function restrictedUsers(): array
    {
        return [
            'unknown approved' => ['auditor', true],
            'empty role' => ['', true],
            'pending family' => ['familiar', false],
            'pending professional' => ['profesional', false],
            'pending family alias' => ['cuidador_familiar', false],
            'pending professional alias' => ['cuidador_profesional', false],
        ];
    }

    #[DataProvider('roleBoundaries')]
    public function test_roles_cannot_cross_into_another_roles_routes(string $role, array $deniedPrefixes): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_approved' => true]));

        foreach ($this->privateApiRoutes() as $route) {
            foreach ($deniedPrefixes as $prefix) {
                if (str_starts_with($route->uri(), $prefix)) {
                    $this->json($route->methods()[0], $this->resourceUrl($route->uri()))->assertForbidden();
                }
            }
        }
    }

    public static function roleBoundaries(): array
    {
        return [
            'family' => ['familiar', ['api/admin/', 'api/professional/', 'api/schedules', 'api/medications/', 'api/mobility-exercises']],
            'family alias' => ['cuidador_familiar', ['api/admin/', 'api/professional/']],
            'professional' => ['profesional', ['api/admin/', 'api/family/']],
            'professional alias' => ['cuidador_profesional', ['api/admin/', 'api/family/']],
            'admin' => ['admin', ['api/family/', 'api/professional/', 'api/medications/']],
        ];
    }

    #[DataProvider('caregiverAliases')]
    public function test_approved_caregiver_aliases_keep_access(string $role, string $overview): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_approved' => true]));

        $this->getJson($overview)->assertOk();
        $this->getJson('/api/incidents')->assertOk();
        $this->getJson('/api/rutinas')->assertOk();
    }

    public static function caregiverAliases(): array
    {
        return [
            ['familiar', '/api/family/overview'],
            ['cuidador_familiar', '/api/family/overview'],
            ['profesional', '/api/professional/overview'],
            ['cuidador_profesional', '/api/professional/overview'],
        ];
    }

    public function test_approval_is_checked_again_with_an_existing_token(): void
    {
        $user = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('access-test')->plainTextToken];
        $this->getJson('/api/professional/overview', $headers)->assertOk();

        $user->update(['is_approved' => false]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/professional/overview', $headers)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_role_cannot_obtain_unfiltered_incidents_from_the_service(): void
    {
        $user = User::factory()->create(['role' => 'auditor', 'is_approved' => true]);
        $this->expectException(HttpResponseException::class);

        app(IncidentListingService::class)->forDate($user, now()->toDateString());
    }

    private function privateApiRoutes(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn ($route) => str_starts_with($route->uri(), 'api/') && ! in_array($route->uri(), self::PUBLIC_API, true)
        ));
    }

    private function resourceUrl(string $uri): string
    {
        return '/'.preg_replace('/\{[^}]+\}/', '999999', $uri);
    }
}

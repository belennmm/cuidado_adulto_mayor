<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SensitiveRouteMethodsTest extends TestCase
{
    use RefreshDatabase;

    private const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function test_guests_cannot_bypass_any_private_route_by_changing_http_methods(): void
    {
        foreach ($this->privatePaths() as $path => $methods) {
            foreach (self::METHODS as $method) {
                $expected = isset($methods[$method]) ? 401 : 405;
                $this->json($method, $path)->assertStatus($expected);
            }
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('older_adults', 0);
    }

    #[DataProvider('roles')]
    public function test_registered_methods_keep_role_boundaries_and_unregistered_methods_cannot_execute(string $role, string $canonical): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('method-matrix')->plainTextToken];
        $checked = 0;
        foreach ($this->privatePaths() as $path => $methods) {
            foreach (self::METHODS as $method) {
                if (! isset($methods[$method])) {
                    $this->json($method, $path, [], $headers)->assertStatus(405);
                    $checked++;
                } elseif ($this->deniesRole($methods[$method], $canonical)) {
                    $this->json($method, $path, ['role' => 'admin', 'is_approved' => true], $headers)->assertForbidden();
                    $checked++;
                }
            }
        }
        $this->assertGreaterThan(100, $checked);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('older_adults', 0);
        $this->assertDatabaseCount('caregiver_schedules', 0);
        $this->assertDatabaseCount('vacation_requests', 0);
    }

    public static function roles(): array
    {
        return [['admin', 'admin'], ['profesional', 'profesional'], ['familiar', 'familiar'], ['cuidador_profesional', 'profesional'], ['cuidador_familiar', 'familiar']];
    }

    public function test_method_overrides_keep_authentication_and_role_authorization(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $family = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $adult = OlderAdult::create(['full_name' => 'Protegido', 'created_by' => $admin->id]);
        $path = '/api/admin/older-adults/'.$adult->id;
        $this->postJson($path, [], ['X-HTTP-Method-Override' => 'DELETE'])->assertUnauthorized();
        $headers = ['Authorization' => 'Bearer '.$family->createToken('override')->plainTextToken];
        foreach (['PUT', 'DELETE'] as $method) {
            $this->postJson($path, ['full_name' => 'Indebido'], [...$headers, 'X-HTTP-Method-Override' => $method])->assertForbidden();
        }

        Request::enableHttpMethodParameterOverride();
        $this->call('POST', $path, ['_method' => 'DELETE'], [], [], ['HTTP_AUTHORIZATION' => $headers['Authorization']])->assertForbidden();
        $this->postJson($path, [], [...$headers, 'X-HTTP-Method-Override' => 'TRACE'])->assertStatus(405);
        $this->assertDatabaseHas('older_adults', ['id' => $adult->id, 'full_name' => 'Protegido']);
        $this->assertDatabaseHas('users', ['id' => $family->id, 'role' => 'familiar']);
    }

    public function test_options_and_disallowed_methods_do_not_expose_or_modify_sensitive_resources(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $adult = OlderAdult::create(['full_name' => 'Protegido', 'created_by' => $admin->id]);
        foreach ($this->privatePaths() as $path => $methods) {
            foreach (['TRACE', 'CONNECT', 'PROPFIND'] as $method) {
                $this->call($method, $path)->assertStatus(405)->assertExactJson(['message' => 'Metodo HTTP no permitido.']);
            }
            $this->call('OPTIONS', $path, [], [], [], ['HTTP_ORIGIN' => 'http://localhost:3000', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE'])
                ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        }
        $this->assertDatabaseHas('older_adults', ['id' => $adult->id, 'full_name' => 'Protegido']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_web_session_alone_cannot_replace_a_bearer_credential(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->actingAs($admin, 'web');
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    private function privatePaths(): array
    {
        $paths = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || ! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                continue;
            }
            $path = '/'.preg_replace('/\{[^}]+\}/', '999999', $route->uri());
            foreach ($route->methods() as $method) {
                $paths[$path][$method] = $route->gatherMiddleware();
            }
        }
        $this->assertNotEmpty($paths);

        // A static URL such as schedules/calendar can match a parameter route under another verb.
        // Ask the router for the actual match instead of assuming the same URI template applies.
        foreach (array_keys($paths) as $path) {
            $paths[$path] = [];
            foreach (self::METHODS as $method) {
                try {
                    $matched = Route::getRoutes()->match(Request::create($path, $method));
                    $paths[$path][$method] = $matched->gatherMiddleware();
                } catch (MethodNotAllowedHttpException|NotFoundHttpException) {
                    // No endpoint executes for this URL and verb.
                }
            }
        }

        return $paths;
    }

    private function deniesRole(array $middleware, string $role): bool
    {
        if (in_array('admin', $middleware, true) && $role !== 'admin') {
            return true;
        }
        foreach ($middleware as $rule) {
            if (str_starts_with($rule, 'role:') && ! in_array($role, explode(',', substr($rule, 5)), true)) {
                return true;
            }
        }

        return false;
    }
}

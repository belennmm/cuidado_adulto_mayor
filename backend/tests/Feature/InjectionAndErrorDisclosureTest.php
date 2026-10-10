<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InjectionAndErrorDisclosureTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('internalFailures')]
    public function test_internal_errors_are_generic_even_with_debug_enabled(string $kind): void
    {
        config(['app.debug' => true]);
        Route::get('/api/disclosure-test', function () use ($kind) {
            $secret = 'SQLSTATE secret_password SELECT * FROM users /srv/private.php';

            return match ($kind) {
                'runtime' => throw new RuntimeException($secret),
                'sql' => throw new QueryException('sqlite', 'SELECT secret_password FROM users', [], new RuntimeException($secret)),
                'http' => abort(503, $secret),
                'forbidden' => abort(403, $secret),
                'response-exception' => throw new HttpResponseException(response()->json(['message' => $secret, 'trace' => [$secret]], 500)),
                default => response($secret, 502),
            };
        });
        $status = match ($kind) {
            'http' => 503, 'forbidden' => 403, 'direct' => 502, default => 500
        };
        $this->getJson('/api/disclosure-test')->assertStatus($status)->assertExactJson([
            'message' => $status === 403 ? 'No tienes permiso para realizar esta accion.' : 'Error interno del servidor.',
        ]);
    }

    public static function internalFailures(): array
    {
        return array_map(fn ($kind) => [$kind], ['runtime', 'sql', 'http', 'forbidden', 'response-exception', 'direct']);
    }

    #[DataProvider('sqlPayloads')]
    public function test_login_injections_cannot_authenticate_or_change_accounts(string $payload): void
    {
        User::factory()->create(['email' => 'victim@example.com', 'is_approved' => true]);
        $this->postJson('/api/login', ['email' => $payload, 'password' => 'Wrong-password!123'])->assertStatus(422);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('sqlPayloads')]
    public function test_sql_in_password_does_not_bypass_the_database_lookup_or_hash_check(string $payload): void
    {
        $user = User::factory()->create(['email' => 'victim@example.com', 'is_approved' => true]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => $payload])->assertUnauthorized();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public static function sqlPayloads(): array
    {
        return [["' OR 1=1 --"], ["victim@example.com' --"], ["x'; DROP TABLE users; --"]];
    }

    public function test_stored_xss_is_returned_as_json_data_without_changing_the_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $headers = ['Authorization' => 'Bearer '.$admin->createToken('xss')->plainTextToken];
        $payload = '<img src=x onerror=alert(1)><script>alert(1)</script>';
        $this->postJson('/api/admin/older-adults', ['full_name' => $payload], $headers)
            ->assertCreated()->assertJsonPath('older_adult.full_name', $payload)
            ->assertHeader('Content-Type', 'application/json');
        $this->getJson('/api/admin/older-adults', $headers)->assertOk()
            ->assertJsonPath('older_adults.0.full_name', $payload)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('older_adults', ['full_name' => $payload]);
    }

    public function test_reflected_attack_in_route_is_not_included_in_the_error(): void
    {
        $this->getJson('/api/'.rawurlencode('<svg onload=alert(1)>'))
            ->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensitiveRequestLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_authenticated_routes_use_the_shared_limiter(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/') && in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $this->assertContains('throttle:sensitive-api', $route->gatherMiddleware(), $route->uri());
            }
        }
    }

    public function test_write_limits_are_shared_across_tokens_and_routes_and_block_persistence(): void
    {
        config(['abuse.writes_per_minute' => 2]);
        $admin = $this->admin();
        $tokens = [$admin->createToken('one')->plainTextToken, $admin->createToken('two')->plainTextToken];
        $this->withToken($tokens[0])->postJson('/api/admin/older-adults', ['full_name' => 'Primero'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens[1])->putJson('/api/me', ['name' => 'Nombre permitido', 'email' => $admin->email])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens[1])->postJson('/api/admin/older-adults', ['full_name' => 'No persistir'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('older_adults', 1);
        $this->assertDatabaseMissing('older_adults', ['full_name' => 'No persistir']);
        $this->withToken($tokens[1])->postJson('/api/logout')->assertOk();
    }

    public function test_read_limits_are_per_account_and_reset_after_the_window(): void
    {
        config(['abuse.reads_per_minute' => 2]);
        $first = $this->admin();
        Sanctum::actingAs($first);
        $this->getJson('/api/admin/users')->assertOk();
        $this->getJson('/api/admin/older-adults')->assertOk();
        $this->getJson('/api/incidents')->assertStatus(429)->assertHeader('Retry-After');
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/users')->assertOk();
        $this->travel(61)->seconds();
        Sanctum::actingAs($first);
        $this->getJson('/api/admin/users')->assertOk();
    }

    public function test_critical_operations_share_a_stricter_budget(): void
    {
        config(['abuse.critical_per_minute' => 1]);
        Sanctum::actingAs($this->admin());
        $first = User::factory()->create(['role' => 'familiar', 'is_approved' => false]);
        $second = User::factory()->create(['role' => 'profesional', 'is_approved' => false]);
        $this->patchJson('/api/admin/users/'.$first->id.'/approve')->assertOk();
        $this->deleteJson('/api/admin/users/'.$second->id)->assertStatus(429);
        $this->assertDatabaseHas('users', ['id' => $second->id, 'is_approved' => false]);
    }

    public function test_hourly_budget_blocks_sustained_writes_after_minute_windows_reset(): void
    {
        config(['abuse.writes_per_hour' => 2]);
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/older-adults', ['full_name' => 'Uno'])->assertCreated();
        $this->travel(61)->seconds();
        $this->postJson('/api/admin/older-adults', ['full_name' => 'Dos'])->assertCreated();
        $this->travel(61)->seconds();
        $this->postJson('/api/admin/older-adults', ['full_name' => 'Tres'])->assertStatus(429);
        $this->assertDatabaseCount('older_adults', 2);
    }

    public function test_login_budget_cannot_be_evaded_by_rotating_email_addresses(): void
    {
        config(['abuse.login_per_minute' => 2]);
        foreach (['one@example.com', 'two@example.com'] as $email) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'three@example.com', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_performance_accounts_bypass_login_burst_limit_only_in_test_environment(): void
    {
        config(['abuse.login_per_minute' => 1]);
        $user = User::factory()->approvedFamily()->create([
            'email' => 'perf-family-001@performance.test',
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $ordinary = User::factory()->approvedFamily()->create();
        $this->postJson('/api/login', ['email' => $ordinary->email, 'password' => 'password'])->assertOk();
        $this->postJson('/api/login', ['email' => $ordinary->email, 'password' => 'password'])->assertStatus(429);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_approved' => true]);
    }
}

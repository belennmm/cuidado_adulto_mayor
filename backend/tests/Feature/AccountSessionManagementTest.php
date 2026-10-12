<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function freshRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_api_sessions_are_limited_and_evicted_tokens_cannot_be_reused(): void
    {
        config(['session.max_per_user' => 2]);
        $user = User::factory()->approvedProfessional()->create();
        $first = $this->headers($user);
        $this->travel(1)->seconds();
        $second = $this->headers($user);
        $this->travel(1)->seconds();
        $third = $this->headers($user);
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseCount('account_sessions', 2);
        $this->getJson('/api/me', $first)->assertUnauthorized();
        $this->freshRequest();
        $this->getJson('/api/me', $second)->assertOk();
        $this->freshRequest();
        $this->getJson('/api/me', $third)->assertOk();
    }

    public function test_account_session_identifiers_are_opaque_and_foreign_sessions_are_hidden(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $other = User::factory()->approvedProfessional()->create();
        $headers = $this->headers($user);
        $foreign = $this->headers($other);
        $rows = $this->getJson('/api/sessions', $headers)->assertOk()->assertJsonCount(1, 'sessions')->json('sessions');
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $rows[0]['id']);
        $this->assertTrue($rows[0]['current']);
        foreach (['token_id', 'token', 'browser_hash', 'user_id', 'security_fingerprint'] as $key) {
            $this->assertArrayNotHasKey($key, $rows[0]);
        }
        $foreignId = DB::table('account_sessions')->where('user_id', $other->id)->value('id');
        $this->freshRequest();
        $foreignResponse = $this->deleteJson('/api/sessions/'.$foreignId, [], $headers)->assertNotFound()->json();
        $this->freshRequest();
        $missing = $this->deleteJson('/api/sessions/11111111-1111-4111-8111-111111111111', [], $headers)->assertNotFound()->json();
        $this->assertSame($foreignResponse, $missing);
        $this->freshRequest();
        $this->getJson('/api/me', $foreign)->assertOk();
    }

    public function test_api_metadata_is_updated_and_selected_token_is_revoked(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $first = $this->headers($user);
        $second = $this->headers($user);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.8'])->getJson('/api/sessions', $first + ['User-Agent' => 'Test device'])->assertOk();
        $row = DB::table('account_sessions')->where('user_id', $user->id)->where('user_agent', 'Test device')->first();
        $this->assertSame('192.0.2.8', $row->ip_address);
        $this->freshRequest();
        $this->deleteJson('/api/sessions/'.$row->id, [], $second)->assertOk()->assertJsonPath('current', false);
        $this->freshRequest();
        $this->getJson('/api/me', $first)->assertUnauthorized();
        $this->freshRequest();
        $this->getJson('/api/me', $second)->assertOk();
    }

    public function test_reauthentication_is_bound_to_one_credential_and_expires_at_five_minutes(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $first = $this->headers($user);
        $second = $this->headers($user);
        $this->travel(5)->minutes();
        $payload = ['name' => 'Sensitive update', 'email' => 'changed@example.test'];
        $this->putJson('/api/me', $payload, $first)->assertStatus(428);
        $this->freshRequest();
        $this->postJson('/api/reauthenticate', ['current_password' => 'wrong'], $first)->assertUnprocessable();
        $this->freshRequest();
        $this->postJson('/api/reauthenticate', ['current_password' => 'password'], $first)->assertOk();
        $this->freshRequest();
        $this->putJson('/api/me', $payload, $second)->assertStatus(428);
        $this->freshRequest();
        $this->putJson('/api/me', $payload, $first)->assertOk();
    }

    public function test_reauthentication_is_limited_and_cannot_modify_profile(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $headers = $this->headers($user);
        $this->postJson('/api/reauthenticate', ['current_password' => 'password', 'role' => 'admin'], $headers)->assertUnprocessable();
        for ($i = 0; $i < 4; $i++) {
            $this->freshRequest();
            $this->postJson('/api/reauthenticate', ['current_password' => 'wrong'], $headers)->assertUnprocessable();
        }
        $this->freshRequest();
        $this->postJson('/api/reauthenticate', ['current_password' => 'password'], $headers)->assertTooManyRequests();
        $this->assertSame('profesional', $user->fresh()->role);
    }

    public function test_credential_activity_does_not_extend_absolute_expiration(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $headers = $this->headers($user);
        for ($i = 0; $i < 5; $i++) {
            $this->travel(10)->minutes();
            $this->freshRequest();
            $this->getJson('/api/me', $headers)->assertOk();
        }
        $this->travel(10)->minutes();
        $this->freshRequest();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
    }

    public function test_migration_tracks_existing_tokens_without_inventing_recent_authentication(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $headers = $this->headers($user);
        $migration = require database_path('migrations/2026_10_11_000002_create_account_sessions_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('account_sessions', 1);
        $this->getJson('/api/me', $headers)->assertOk();
        $this->freshRequest();
        $this->putJson('/api/me', ['name' => 'Change', 'email' => $user->email], $headers)->assertStatus(428);
        $this->freshRequest();
        $this->postJson('/api/reauthenticate', ['current_password' => 'password'], $headers)->assertOk();
    }

    public function test_listing_removes_expired_credentials_and_never_exposes_another_account(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $short = $user->createToken('short', ['*'], now()->addMinute());
        $headers = $this->headers($user);
        $other = User::factory()->approvedProfessional()->create();
        $this->headers($other);
        $this->travel(1)->minutes();
        $this->getJson('/api/sessions', $headers)->assertOk()->assertJsonCount(1,'sessions');
        $this->assertDatabaseMissing('personal_access_tokens',['id' => $short->accessToken->id]);
        $this->assertDatabaseHas('account_sessions',['user_id' => $other->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context) {
            $this->assertSame('security.event', $message);
            $this->events[] = $context;
        });
    }

    public function test_successful_login_records_the_actor_without_credentials_or_token(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $response = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password',
        ])->assertOk();

        $event = $this->assertEvent($response, 'authentication.login', 'success', $user->id);
        $this->assertSame('api/login', $event['resource_id']);
        $serialized = json_encode($event, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($user->email, $serialized);
        $this->assertStringNotContainsString('password', $serialized);
        $this->assertStringNotContainsString($response->json('token'), $serialized);
    }

    public function test_invalid_credentials_record_a_denial_without_claiming_an_actor(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $response = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $this->assertEvent($response, 'authentication.login', 'denied', null);
    }

    public function test_pending_accounts_record_a_login_denial(): void
    {
        $user = User::factory()->pendingProfessional()->create();
        $response = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password',
        ])->assertForbidden();

        $this->assertEvent($response, 'authentication.login', 'denied', null);
    }

    public function test_a_login_lockout_is_recorded_once_with_its_own_request_id(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'audit-lockout@example.com', 'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->assertCount(5, $this->events);
        $this->events = [];
        $response = $this->postJson('/api/login', [
            'email' => 'audit-lockout@example.com', 'password' => 'incorrect-password',
        ])->assertStatus(429);

        $this->assertEvent($response, 'authentication.login', 'blocked', null);
    }

    public function test_unauthenticated_access_is_recorded_once(): void
    {
        $response = $this->getJson('/api/admin/users')->assertUnauthorized();

        $this->assertEvent($response, 'authentication.required', 'denied', null);
    }

    public function test_an_incorrect_role_is_recorded_with_the_authenticated_actor(): void
    {
        $user = User::factory()->approvedFamily()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/admin/users')->assertForbidden();

        $this->assertEvent($response, 'authorization.denied', 'denied', $user->id);
    }

    public function test_logout_is_recorded_and_revokes_the_token(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('audit-test', ['*']);
        $response = $this->withToken($token->plainTextToken)->postJson('/api/logout')->assertOk();

        $this->assertEvent($response, 'authentication.logout', 'success', $user->id);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_public_success_does_not_create_an_authentication_event(): void
    {
        $this->getJson('/api/ping')->assertOk();

        $this->assertSame([], $this->events);
    }

    private function assertEvent(TestResponse $response, string $name, string $result, ?int $actorId): array
    {
        $this->assertCount(1, $this->events);
        $event = $this->events[0];
        $this->assertSame($name, $event['event']);
        $this->assertSame($result, $event['result']);
        $this->assertSame($actorId, $event['actor_id']);
        $this->assertSame($response->headers->get('X-Correlation-ID'), $event['correlation_id']);
        $this->assertNotEmpty($event['correlation_id']);
        $this->assertSame('127.0.0.0', $event['ip_address']);

        return $event;
    }
}

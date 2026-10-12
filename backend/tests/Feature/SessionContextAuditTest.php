<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SessionContextAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('channel')->with('security_audit')->andReturnSelf();
        Log::shouldReceive('log')->andReturnUsing(function (string $level, string $message, array $context) {
            if (str_starts_with($context['event'] ?? '', 'session.')) {
                $this->assertSame('warning', $level);
                $this->assertSame('security.event', $message);
                $this->events[] = $context;
            }
        });
    }

    public function test_login_initializes_private_fingerprints_and_unchanged_requests_do_not_alert(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('User-Agent', 'Browser-A')->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $token = PersonalAccessToken::query()->sole();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token->session_ip_fingerprint);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token->session_agent_fingerprint);
        $this->assertArrayNotHasKey('session_ip_fingerprint', $token->toArray());
        $this->assertArrayNotHasKey('session_agent_fingerprint', $token->toArray());
        $this->withToken($response->json('token'))->getJson('/api/me')->assertOk();
        $this->getJson('/api/me')->assertOk();
        $this->assertSame([], $this->events);
    }

    public function test_ip_and_user_agent_changes_are_correlated_without_leaking_context_or_token(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHour());
        $this->withToken($token->plainTextToken)->withHeader('User-Agent', 'Browser-A')
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->getJson('/api/me')->assertOk();
        $response = $this->withHeader('User-Agent', 'Browser-B')->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->getJson('/api/me')->assertOk();
        $this->assertSame(['session.ip_changed', 'session.user_agent_changed'], array_column($this->events, 'event'));
        foreach ($this->events as $event) {
            $this->assertSame($user->id, $event['actor_id']);
            $this->assertSame('triggered', $event['result']);
            $this->assertSame('session', $event['resource_type']);
            $this->assertNull($event['resource_id']);
            $this->assertSame('198.51.100.0', $event['ip_address']);
            $this->assertSame($response->headers->get('X-Correlation-ID'), $event['correlation_id']);
        }
        $encoded = json_encode($this->events, JSON_THROW_ON_ERROR);
        foreach (['Browser-A', 'Browser-B', '192.0.2.10', '198.51.100.20', $token->plainTextToken,
            $token->accessToken->fresh()->session_ip_fingerprint, $token->accessToken->fresh()->session_agent_fingerprint] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->getJson('/api/me')->assertOk();
        $this->assertCount(2, $this->events);
    }

    public function test_separate_tokens_for_the_same_user_do_not_mix_devices(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $first = $user->createToken('first', ['*'], now()->addHour());
        $second = $user->createToken('second', ['*'], now()->addHour());
        $this->withToken($first->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        $this->withToken($second->plainTextToken)->withHeader('User-Agent', 'Browser-B')->getJson('/api/me')->assertOk();
        $this->withToken($first->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        $this->assertSame([], $this->events);
    }

    public function test_equivalent_ip_representations_do_not_trigger_changes(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHour());
        $this->withToken($token->plainTextToken)->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->getJson('/api/me')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '::ffff:192.0.2.10'])->getJson('/api/me')->assertOk();
        $this->assertSame([], $this->events);
    }

    public function test_invalid_user_agent_is_not_stored_or_used_to_reset_the_baseline(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHour());
        $this->withToken($token->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        $fingerprint = $token->accessToken->fresh()->session_agent_fingerprint;
        $this->withHeader('User-Agent', "forged\nlog")->getJson('/api/me')->assertOk();
        $this->assertSame($fingerprint, $token->accessToken->fresh()->session_agent_fingerprint);
        $this->assertSame([], $this->events);
    }

    public function test_expired_and_revoked_tokens_cannot_update_session_context(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHour());
        $this->withToken($token->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        $fingerprint = $token->accessToken->fresh()->session_agent_fingerprint;
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->withHeader('User-Agent', 'Browser-B')->getJson('/api/me')->assertUnauthorized();
        $this->assertSame($fingerprint, $token->accessToken->fresh()->session_agent_fingerprint);
        $token->accessToken->delete();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->assertSame([], $this->events);
    }
}

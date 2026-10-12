<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionContextAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['security_audit.session_cache_store' => 'array']);
        Log::shouldReceive('channel')->with('security_audit')->andReturnSelf();
        Log::shouldReceive('log')->andReturnUsing(function (string $level, string $message, array $context) {
            if (str_starts_with($context['event'] ?? '', 'session.')) {
                $this->assertSame($context['result'] === 'failure' ? 'error' : 'warning', $level);
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
        $context = Cache::store('array')->get($this->cacheKey($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['ip']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['agent']);
        $this->assertFalse(Schema::hasColumn('personal_access_tokens', 'session_ip_fingerprint'));
        $this->assertFalse(Schema::hasColumn('personal_access_tokens', 'session_agent_fingerprint'));
        $this->assertStringNotContainsString($context['ip'], $response->getContent());
        $this->assertStringNotContainsString($context['agent'], $response->getContent());
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
        $context = Cache::store('array')->get($this->cacheKey($token->accessToken));
        foreach (['Browser-A', 'Browser-B', '192.0.2.10', '198.51.100.20', $token->plainTextToken,
            $context['ip'], $context['agent']] as $secret) {
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
        $fingerprint = Cache::store('array')->get($this->cacheKey($token->accessToken))['agent'];
        $this->withHeader('User-Agent', "forged\nlog")->getJson('/api/me')->assertOk();
        $this->assertSame($fingerprint, Cache::store('array')->get($this->cacheKey($token->accessToken))['agent']);
        $this->assertSame([], $this->events);
    }

    public function test_expired_and_revoked_tokens_cannot_update_session_context(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHour());
        $this->withToken($token->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        $fingerprint = Cache::store('array')->get($this->cacheKey($token->accessToken))['agent'];
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->withHeader('User-Agent', 'Browser-B')->getJson('/api/me')->assertUnauthorized();
        $this->assertSame($fingerprint, Cache::store('array')->get($this->cacheKey($token->accessToken))['agent']);
        $token->accessToken->delete();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->assertSame([], $this->events);
    }

    public function test_cleared_cache_establishes_a_new_reference_then_detects_subsequent_changes(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHour());
        $this->withToken($token->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        Cache::store('array')->forget($this->cacheKey($token->accessToken));
        $this->withHeader('User-Agent', 'Browser-B')->getJson('/api/me')->assertOk();
        $this->assertSame([], $this->events);
        $this->withHeader('User-Agent', 'Browser-C')->getJson('/api/me')->assertOk();
        $this->assertSame(['session.user_agent_changed'], array_column($this->events, 'event'));
    }

    public function test_reference_expires_after_one_hour_without_activity(): void
    {
        config(['sanctum.expiration' => 180, 'session.idle_timeout' => 180, 'session.absolute_timeout' => 180]);
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addHours(3));
        $this->withToken($token->plainTextToken)->withHeader('User-Agent', 'Browser-A')->getJson('/api/me')->assertOk();
        try {
            $this->travel(3601)->seconds();
            $this->assertNull(Cache::store('array')->get($this->cacheKey($token->accessToken)));
            $this->withHeader('User-Agent', 'Browser-B')->getJson('/api/me')->assertOk();
            $this->assertSame([], $this->events);
        } finally {
            $this->travelBack();
        }
    }

    public function test_cache_failure_does_not_prevent_login_and_records_degraded_detection(): void
    {
        $user = User::factory()->approvedFamily()->create();
        config(['security_audit.session_cache_store' => 'audit_failure']);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame(['session.context_unavailable'], array_column($this->events, 'event'));
        $this->assertSame($user->id, $this->events[0]['actor_id']);
        $this->assertStringNotContainsString('private-cache-secret', json_encode($this->events, JSON_THROW_ON_ERROR));
    }

    public function test_reference_does_not_outlive_token_expiration(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $token = $user->createToken('session', ['*'], now()->addSeconds(10));
        $this->withToken($token->plainTextToken)->getJson('/api/me')->assertOk();
        $this->assertNotNull(Cache::store('array')->get($this->cacheKey($token->accessToken)));
        try {
            $this->travel(11)->seconds();
            $this->assertNull(Cache::store('array')->get($this->cacheKey($token->accessToken)));
            $this->getJson('/api/me')->assertUnauthorized();
            $this->assertSame([], $this->events);
        } finally {
            $this->travelBack();
        }
    }

    private function cacheKey(PersonalAccessToken $token): string
    {
        return 'security-session:'.hash_hmac('sha256', $token->getKey().':'.$token->token, (string) config('app.key'));
    }
}

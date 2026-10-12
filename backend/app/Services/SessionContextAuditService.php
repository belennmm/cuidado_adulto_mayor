<?php

namespace App\Services;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SessionContextAuditService
{
    public function initialize(PersonalAccessToken $token, Request $request): void
    {
        $this->synchronize($token, $request, false);
    }

    public function observe(PersonalAccessToken $token, Request $request): void
    {
        if ($request->attributes->get('session_context_audited')) {
            return;
        }
        $request->attributes->set('session_context_audited', true);
        $changes = $this->synchronize($token, $request, true);

        foreach ($changes as $change) {
            app(SecurityEventRecorder::class)->record(
                $request, 'session.'.$change, 'detect', 'triggered',
                (int) $request->user()->getAuthIdentifier(), 'session', null,
            );
        }
    }

    private function synchronize(PersonalAccessToken $token, Request $request, bool $compare): array
    {
        try {
            $cache = Cache::store(config('security_audit.session_cache_store', 'file'));
            $key = 'security-session:'.hash_hmac('sha256', $token->getKey().':'.$token->token, (string) config('app.key'));
            $ttl = 3600;
            if ($token->expires_at !== null) {
                $ttl = min($ttl, max(1, $token->expires_at->getTimestamp() - now()->getTimestamp()));
            }
            $expiration = (int) config('sanctum.expiration');
            if ($expiration > 0) {
                $ttl = min($ttl, max(1, $token->created_at->getTimestamp() + $expiration * 60 - now()->getTimestamp()));
            }

            return $cache->lock($key.':lock', 10)->block(2, function () use ($cache, $key, $ttl, $request, $compare) {
                $stored = $compare ? $cache->get($key, []) : [];
                $stored = is_array($stored) ? $stored : [];
                $current = $this->fingerprints($request);
                $changes = [];
                foreach ($current as $field => $value) {
                    $previous = $stored[$field] ?? null;
                    if (! is_string($previous) || ! preg_match('/\A[a-f0-9]{64}\z/', $previous)) {
                        $previous = null;
                    }
                    if ($value !== null && $previous !== null && ! hash_equals($previous, $value)) {
                        $changes[] = $field === 'ip' ? 'ip_changed' : 'user_agent_changed';
                    }
                    $current[$field] = $value ?? $previous;
                }
                if (! $cache->put($key, $current, $ttl)) {
                    throw new RuntimeException('Session context write unavailable.');
                }

                return $changes;
            });
        } catch (Throwable) {
            error_log('security_session_context_unavailable');
            app(SecurityEventRecorder::class)->record(
                $request, 'session.context_unavailable', 'detect', 'failure',
                (int) $token->tokenable_id, 'session', null,
            );

            return [];
        }
    }

    private function fingerprints(Request $request): array
    {
        $ip = $request->ip();
        $packed = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? inet_pton($ip) : null;
        if ($packed !== null && strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }
        $agent = $request->userAgent();
        if (! is_string($agent) || $agent === '' || strlen($agent) > 1024
            || preg_match('/[\x00-\x1F\x7F-\x{009F}\x{2028}\x{2029}]/u', $agent) !== 0) {
            $agent = null;
        }
        $key = (string) config('app.key');

        return [
            'ip' => $packed === null ? null : hash_hmac('sha256', 'ip:'.$packed, $key),
            'agent' => $agent === null ? null : hash_hmac('sha256', 'agent:'.$agent, $key),
        ];
    }
}

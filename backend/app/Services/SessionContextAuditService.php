<?php

namespace App\Services;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionContextAuditService
{
    public function initialize(PersonalAccessToken $token, Request $request): void
    {
        $token->forceFill($this->fingerprints($request))->save();
    }

    public function observe(PersonalAccessToken $token, Request $request): void
    {
        if ($request->attributes->get('session_context_audited')) {
            return;
        }
        $request->attributes->set('session_context_audited', true);
        $fingerprints = $this->fingerprints($request);
        $changes = DB::transaction(function () use ($token, $fingerprints) {
            $stored = PersonalAccessToken::query()->whereKey($token->getKey())->lockForUpdate()->first();
            if (! $stored) {
                return [];
            }
            $changes = [];
            foreach ($fingerprints as $field => $value) {
                if ($value === null) {
                    continue;
                }
                if ($stored->{$field} !== null && ! hash_equals($stored->{$field}, $value)) {
                    $changes[] = $field === 'session_ip_fingerprint' ? 'ip_changed' : 'user_agent_changed';
                }
                $stored->{$field} = $value;
            }
            if ($stored->isDirty()) {
                $stored->save();
            }

            return $changes;
        });

        foreach ($changes as $change) {
            app(SecurityEventRecorder::class)->record(
                $request, 'session.'.$change, 'detect', 'triggered',
                (int) $request->user()->getAuthIdentifier(), 'session', null,
            );
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
            'session_ip_fingerprint' => $packed === null ? null : hash_hmac('sha256', 'ip:'.$packed, $key),
            'session_agent_fingerprint' => $agent === null ? null : hash_hmac('sha256', 'agent:'.$agent, $key),
        ];
    }
}

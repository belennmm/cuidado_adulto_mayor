<?php

namespace App\Models;

use App\Services\AccountSessionService;
use App\Support\AccountSecurityState;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    protected $hidden = ['token', 'security_fingerprint', 'session_ip_fingerprint', 'session_agent_fingerprint'];

    public static function findToken($token)
    {
        // Both supported formats select a candidate, then compare fixed-length digests.
        if (str_contains($token, '|')) {
            [$id,$plain] = explode('|', $token, 2);
            if (! ctype_digit($id) || strlen($id) > strlen((string) PHP_INT_MAX) || (strlen($id) === strlen((string) PHP_INT_MAX) && strcmp($id, (string) PHP_INT_MAX) > 0)) {
                return null;
            }
            $instance = static::find($id);
        } else {
            $plain = $token;
            $instance = static::where('token', hash('sha256', $plain))->first();
        }
        $matches = hash_equals($instance?->token ?? str_repeat('0', 64), hash('sha256', $plain));

        return $matches ? $instance : null;
    }

    protected static function booted(): void
    {
        static::created(function (self $token) {
            $user = $token->tokenable()->first();
            if ($user instanceof User) {
                app(AccountSessionService::class)->start($user, $token->id, app('request'));
            }
        });
        static::creating(function (self $token) {
            $user = $token->tokenable()->first();
            if ($user instanceof User) {
                $token->security_fingerprint = AccountSecurityState::fingerprint($user);
            }
        });
    }
}

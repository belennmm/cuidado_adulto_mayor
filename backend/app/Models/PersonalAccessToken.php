<?php

namespace App\Models;

use App\Support\AccountSecurityState;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    protected $hidden = ['token', 'security_fingerprint'];

    protected static function booted(): void
    {
        static::creating(function (self $token) {
            $user = $token->tokenable()->first();
            if ($user instanceof User) {
                $token->security_fingerprint = AccountSecurityState::fingerprint($user);
            }
        });
    }
}

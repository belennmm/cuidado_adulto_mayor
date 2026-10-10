<?php

namespace App\Support;

use App\Models\User;

final class AccountSecurityState
{
    public static function fingerprint(User $user): string
    {
        return hash('sha256', json_encode([
            $user->getKey(), $user->role, (bool) $user->is_approved, $user->password,
        ], JSON_THROW_ON_ERROR));
    }
}

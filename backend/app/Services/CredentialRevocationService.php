<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CredentialRevocationService
{
    public function revoke(User $user, ?string $previousEmail = null): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->whereIn('email', array_filter([$user->email, $previousEmail]))->delete();
    }
}

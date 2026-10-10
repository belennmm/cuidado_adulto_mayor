<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccountSecurityState;
use Illuminate\Support\Facades\DB;

class CredentialRevocationService
{
    public function revoke(User $user, ?string $previousEmail = null): void
    {
        $user->tokens()->delete();
        $this->revokeSessionsAndResets($user, $previousEmail);
    }

    public function revokeOutdated(User $user): void
    {
        $fingerprint = AccountSecurityState::fingerprint($user);
        $user->tokens()->where(fn ($query) => $query
            ->whereNull('security_fingerprint')->orWhere('security_fingerprint', '!=', $fingerprint))->delete();
        $this->revokeSessionsAndResets($user);
    }

    private function revokeSessionsAndResets(User $user, ?string $previousEmail = null): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->whereIn('email', array_filter([$user->email, $previousEmail]))->delete();
    }
}

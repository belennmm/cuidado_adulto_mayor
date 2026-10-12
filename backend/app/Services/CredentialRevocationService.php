<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccountSecurityState;
use Illuminate\Support\Facades\DB;

class CredentialRevocationService
{
    public function revoke(User $user, ?string $previousEmail = null): void
    {
        DB::transaction(function () use ($user, $previousEmail) {
            DB::table('users')->where('id', $user->getKey())->increment('session_version');
            $user->setAttribute('session_version', (int) DB::table('users')->where('id', $user->getKey())->value('session_version'));
            $user->syncOriginalAttribute('session_version');
            $user->tokens()->delete();
            $this->revokeSessionsAndResets($user, $previousEmail);
        });
    }

    public function revokeOutdated(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $fingerprint = AccountSecurityState::fingerprint($user);
            $user->tokens()->where(fn ($query) => $query
                ->whereNull('security_fingerprint')->orWhere('security_fingerprint', '!=', $fingerprint))->delete();
            DB::table('account_sessions')->where('user_id', $user->getKey())->whereNull('token_id')
                ->where(fn ($query) => $query->whereNull('security_fingerprint')->orWhere('security_fingerprint', '!=', $fingerprint))->delete();
            $validHashes = DB::table('account_sessions')->where('user_id', $user->getKey())->whereNull('token_id')->pluck('browser_hash')->all();
            foreach (DB::table('sessions')->where('user_id', $user->getKey())->get(['id']) as $session) {
                if (! in_array(hash('sha256', $session->id), $validHashes, true)) {
                    DB::table('sessions')->where('id', $session->id)->delete();
                }
            }
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });
    }

    private function revokeSessionsAndResets(User $user, ?string $previousEmail = null): void
    {
        DB::table('mfa_challenges')->where('user_id', $user->getKey())->delete();
        DB::table('account_sessions')->where('user_id', $user->getKey())->delete();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->whereIn('email', array_filter([$user->email, $previousEmail]))->delete();
    }
}

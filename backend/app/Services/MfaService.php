<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccountSecurityState;
use App\Support\ResourceAccess;
use App\Support\TokenAbilities;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;

class MfaService
{
    private function active(User $user): bool
    {
        return ResourceAccess::admin($user) || ResourceAccess::caregiver($user);
    }

    public function begin(User $user, Request $request): array
    {
        abort_if(RateLimiter::tooManyAttempts('mfa-account:'.$user->id, 5), 429);
        DB::table('mfa_challenges')->where('user_id', $user->id)->delete();
        $challenge = bin2hex(random_bytes(32));
        DB::table('mfa_challenges')->insert(['id' => hash('sha256', $challenge), 'user_id' => $user->id, 'security_fingerprint' => AccountSecurityState::fingerprint($user), 'browser_hash' => $request->attributes->get('browser_session') ? hash('sha256', $request->session()->getId()) : null, 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);

        return ['mfa_required' => true, 'challenge' => $challenge, 'expires_in' => 300];
    }

    private function factor(User $user, string $code): bool
    {
        if (! $user->mfa_secret) {
            return false;
        }
        $step = Totp::match($user->mfa_secret, $code, (int) $user->mfa_last_step);
        $matched = null;
        foreach (DB::table('mfa_recovery_codes')->where('user_id', $user->id)->get() as $row) {
            $valid = hash_equals($row->code_hash, hash('sha256', $code));
            if ($valid && Carbon::parse($row->expires_at)->gt(now())) {
                $matched = $row;
            }
        }
        if ($step !== null) {
            $user->forceFill(['mfa_last_step' => $step])->save();

            return true;
        }
        if ($matched) {
            DB::table('mfa_recovery_codes')->where('id', $matched->id)->delete();

            return true;
        }

        return false;
    }

    public function complete(Request $request, array $data): ?array
    {
        return (new Timebox)->call(fn () => DB::transaction(function () use ($request, $data) {
            $id = hash('sha256', $data['challenge']);
            $row = DB::table('mfa_challenges')->where('id', $id)->first();
            if (! $row) {
                return null;
            }
            $user = User::query()->whereKey($row->user_id)->lockForUpdate()->first();
            $row = DB::table('mfa_challenges')->where('id', $id)->first();
            if (! $user || ! $row) {
                return null;
            }
            if (! $this->active($user) || ! $user->mfa_enabled_at || ! hash_equals(AccountSecurityState::fingerprint($user), $row->security_fingerprint) || Carbon::parse($row->expires_at)->lte(now())) {
                DB::table('mfa_challenges')->where('id', $id)->delete();

                return null;
            }
            if (! hash_equals($row->id, $id)) {
                return null;
            }
            $browser = (bool) $request->attributes->get('browser_session');
            if (($row->browser_hash !== null) !== $browser || ($browser && ! hash_equals($row->browser_hash, hash('sha256', $request->session()->getId())))) {
                return null;
            }
            if ($row->attempts >= 5 || RateLimiter::tooManyAttempts('mfa-account:'.$user->id, 5)) {
                return null;
            }
            if (! $this->factor($user, $data['code'])) {
                DB::table('mfa_challenges')->where('id', $id)->increment('attempts');
                RateLimiter::hit('mfa-account:'.$user->id, 300);

                return null;
            }
            DB::table('mfa_challenges')->where('id', $id)->delete();

            return ['user' => $user, 'token' => $browser ? null : $user->createToken('API Token', TokenAbilities::forUser($user), now()->addMinutes((int) config('sanctum.expiration', 60)))->plainTextToken];
        }), 200000);
    }

    private function rotateCodes(User $user): array
    {
        DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = bin2hex(random_bytes(16));
            $codes[] = $code;
            DB::table('mfa_recovery_codes')->insert(['user_id' => $user->id, 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addDays(7), 'created_at' => now()]);
        }

        return $codes;
    }

    public function manage(Request $request, string $action, array $data): ?array
    {
        return DB::transaction(function () use ($request, $action, $data) {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->active($user) && hash_equals(AccountSecurityState::fingerprint($user), AccountSecurityState::fingerprint($request->user())), 401);
            app(AccountSessionService::class)->confirm($request, $data['current_password']);
            if (RateLimiter::tooManyAttempts('mfa-account:'.$user->id, 5)) {
                return null;
            }
            if ($action === 'setup') {
                if ($user->mfa_enabled_at && ! $this->factor($user, $data['code'] ?? '')) {
                    RateLimiter::hit('mfa-account:'.$user->id, 300);

                    return null;
                }
                $secret = Totp::secret();
                $user->forceFill(['mfa_pending_secret' => $secret, 'mfa_pending_expires_at' => now()->addMinutes(10), 'mfa_setup_attempts' => 0])->save();

                return ['secret' => $secret, 'expires_in' => 600, 'message' => 'Introduce esta clave manualmente en tu aplicacion autenticadora.'];
            }
            if ($action === 'confirm') {
                if (! $user->mfa_pending_secret || ! $user->mfa_pending_expires_at || $user->mfa_pending_expires_at->lte(now()) || $user->mfa_setup_attempts >= 5) {
                    return null;
                }
                $step = Totp::match($user->mfa_pending_secret, $data['code']);
                if ($step === null) {
                    $user->forceFill(['mfa_setup_attempts' => $user->mfa_setup_attempts + 1])->save();
                    RateLimiter::hit('mfa-account:'.$user->id, 300);

                    return null;
                }
                $user->forceFill(['mfa_secret' => $user->mfa_pending_secret, 'mfa_enabled_at' => now(), 'mfa_last_step' => $step, 'mfa_pending_secret' => null, 'mfa_pending_expires_at' => null, 'mfa_setup_attempts' => 0])->save();

                return ['recovery_codes' => $this->rotateCodes($user), 'message' => 'MFA activado. Guarda los codigos fuera de la aplicacion e inicia sesion nuevamente.'];
            }
            if (! $user->mfa_enabled_at || ! $this->factor($user, $data['code'])) {
                RateLimiter::hit('mfa-account:'.$user->id, 300);

                return null;
            }
            if ($action === 'disable') {
                $user->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null, 'mfa_pending_secret' => null, 'mfa_pending_expires_at' => null, 'mfa_last_step' => -1])->save();
                DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();

                return ['message' => 'MFA desactivado. Inicia sesion nuevamente.'];
            }
            $codes = $this->rotateCodes($user);
            app(CredentialRevocationService::class)->revoke($user);

            return ['recovery_codes' => $codes, 'message' => 'Codigos regenerados. Los anteriores dejaron de funcionar. Inicia sesion nuevamente.'];
        });
    }

    public function status(User $user): array
    {
        return ['enabled' => (bool) $user->mfa_enabled_at, 'recovery_codes_remaining' => DB::table('mfa_recovery_codes')->where('user_id',$user->id)->where('expires_at','>',now())->count()];
    }
}

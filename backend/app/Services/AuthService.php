<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\OlderAdult;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\ResourceAccess;
use App\Support\TokenAbilities;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function authenticate(string $email, string $password, string $ipAddress = 'unknown', bool $issueToken = true): array
    {
        $rateLimitKey = $this->loginRateLimitKey($email, $ipAddress);
        $limits = [
            $rateLimitKey => (int) config('abuse.login_pair_failures', 5),
            'login-account:'.sha1(Str::lower(trim($email))) => (int) config('abuse.login_account_failures', 10),
            'login-ip:'.sha1($ipAddress) => (int) config('abuse.login_ip_failures', 30),
        ];
        foreach ($limits as $key => $maximum) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $retry = RateLimiter::availableIn($key);
                abort(response()->json([
                    'message' => 'Demasiados intentos fallidos. Intenta nuevamente mas tarde.', 'retry_after' => $retry,
                ], 429, ['Retry-After' => (string) $retry]));
            }
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            foreach ($limits as $key => $maximum) {
                RateLimiter::hit($key, (int) config('abuse.login_lockout_seconds', 300));
            }
            abort(response()->json(['message' => 'Credenciales invalidas'], 401));
        }

        if (! $this->canLogin($user)) {
            foreach ($limits as $key => $maximum) {
                RateLimiter::hit($key, (int) config('abuse.login_lockout_seconds', 300));
            }
            abort(response()->json([
                'message' => 'Tu cuenta esta pendiente de aprobacion por un administrador.',
            ], 403));
        }

        RateLimiter::clear($rateLimitKey);

        $expirationMinutes = (int) config('sanctum.expiration', 60);

        return [
            'user' => $user,
            'token' => $issueToken ? $user->createToken(
                'API Token',
                TokenAbilities::forUser($user),
                now()->addMinutes($expirationMinutes),
            )->plainTextToken : null,
        ];
    }

    private function loginRateLimitKey(string $email, string $ipAddress): string
    {
        return 'login:'.sha1(Str::lower(trim($email)).'|'.$ipAddress);
    }

    public function register(array $data): User
    {
        PasswordPolicy::validate($data['password']);
        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $this->normalizePublicRole($data['role'] ?? null),
            'is_approved' => false,
            'location' => $data['location'] ?? null,
            'phone' => $data['phone'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'privacy_consent_at' => now(),
            'privacy_policy_version' => config('privacy.policy_version'),
        ])->save();

        return $user;
    }

    public function updateProfile(User $user, array $data): User
    {
        // The service enforces the same input boundary as the HTTP request.
        $data = Arr::only($data, [
            'name', 'email', 'location', 'phone', 'birthdate',
            'current_password', 'new_password', 'new_password_confirmation',
        ]);

        if (! empty($data['new_password'])) {
            $this->validateCurrentPassword($user, $data['current_password'] ?? '');
            PasswordPolicy::validate($data['new_password'], 'new_password', $user);
            $data['password'] = Hash::make($data['new_password']);
        }

        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);

        return DB::transaction(function () use ($user, $data) {
            $previousName = $user->name;
            $user->forceFill($data)->save();

            if ($user->hasRole(UserRole::FAMILY) && $previousName !== $user->name) {
                $this->updateFamilyCaregiverName($user);
            }

            return $user->refresh();
        });
    }

    public function revokeAllSessions(User $user, string $currentPassword): void
    {
        DB::transaction(function () use ($user, $currentPassword) {
            $current = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->validateCurrentPassword($current, $currentPassword);
            app(CredentialRevocationService::class)->revoke($current);
        });
    }

    private function validateCurrentPassword(User $user, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contrasena actual no coincide.'],
            ]);
        }
    }

    private function updateFamilyCaregiverName(User $user): void
    {
        OlderAdult::query()
            ->where('family_caregiver_id', $user->id)
            ->update(['caregiver_family' => $user->name]);
    }

    private function normalizePublicRole(?string $role): string
    {
        $normalized = UserRole::fromValue($role);

        return in_array($normalized, [UserRole::FAMILY, UserRole::PROFESSIONAL], true)
            ? $normalized->value
            : UserRole::FAMILY->value;
    }

    private function canLogin(User $user): bool
    {
        return ResourceAccess::admin($user) || ResourceAccess::caregiver($user);
    }
}

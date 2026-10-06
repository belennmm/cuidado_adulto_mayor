<?php

namespace App\Services;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_LOCKOUT_SECONDS = 300;

    public function authenticate(string $email, string $password, string $ipAddress = 'unknown'): array
    {
        $rateLimitKey = $this->loginRateLimitKey($email, $ipAddress);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_LOGIN_ATTEMPTS)) {
            abort(response()->json([
                'message' => 'Demasiados intentos fallidos. Intenta nuevamente mas tarde.',
                'retry_after' => RateLimiter::availableIn($rateLimitKey),
            ], 429));
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($rateLimitKey, self::LOGIN_LOCKOUT_SECONDS);
            abort(response()->json(['message' => 'Credenciales invalidas'], 401));
        }

        if (! $this->canLogin($user)) {
            abort(response()->json([
                'message' => 'Tu cuenta esta pendiente de aprobacion por un administrador.',
            ], 403));
        }

        // A valid authentication resets only this identity/IP failure bucket.
        RateLimiter::clear($rateLimitKey);

        $expirationMinutes = (int) config('sanctum.expiration', 60);

        return [
            'user' => $user,
            'token' => $user->createToken(
                'API Token',
                ['*'],
                now()->addMinutes($expirationMinutes),
            )->plainTextToken,
        ];
    }

    private function loginRateLimitKey(string $email, string $ipAddress): string
    {
        return 'login:'.sha1(Str::lower(trim($email)).'|'.$ipAddress);
    }

    public function register(array $data): User
    {
        return User::create([
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
        ]);
    }

    public function updateProfile(User $user, array $data): User
    {
        $previousName = $user->name;

        if (! empty($data['new_password'])) {
            $this->validateCurrentPassword($user, $data['current_password'] ?? '');
            $data['password'] = Hash::make($data['new_password']);
            $passwordChanged = true;
        }

        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);
        $user->update($data);

        if ($passwordChanged ?? false) {
            $this->revokeCredentials($user);
        }

        if ($this->isFamilyRole($user->role) && $previousName !== $user->name) {
            $this->updateFamilyCaregiverName($user, $previousName);
        }

        return $user->refresh();
    }

    private function revokeCredentials(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }

    private function validateCurrentPassword(User $user, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contrasena actual no coincide.'],
            ]);
        }
    }

    private function updateFamilyCaregiverName(User $user, string $previousName): void
    {
        OlderAdult::query()
            ->where(function ($query) use ($user, $previousName) {
                $query->where('family_caregiver_id', $user->id)
                    ->orWhere(function ($legacyQuery) use ($previousName) {
                        $legacyQuery->whereNull('family_caregiver_id')
                            ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower($previousName)]);
                    });
            })
            ->update(['caregiver_family' => $user->name]);
    }

    private function normalizePublicRole(?string $role): string
    {
        return match ($role) {
            'profesional', 'cuidador_profesional' => 'profesional',
            'familiar', 'cuidador_familiar' => 'familiar',
            default => 'familiar',
        };
    }

    private function canLogin(User $user): bool
    {
        return strtolower(trim((string) $user->role)) === 'admin' || (bool) $user->is_approved;
    }

    private function isFamilyRole(?string $role): bool
    {
        return in_array(strtolower(trim((string) $role)), ['familiar', 'cuidador_familiar'], true);
    }
}

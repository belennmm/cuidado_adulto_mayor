<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\OlderAdult;
use App\Models\User;
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

        RateLimiter::clear($rateLimitKey);

        $expirationMinutes = (int) config('sanctum.expiration', 60);

        return [
            'user' => $user,
            'token' => $user->createToken(
                'API Token',
                TokenAbilities::forUser($user),
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

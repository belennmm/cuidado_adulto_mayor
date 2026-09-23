<?php

namespace App\Services;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function authenticate(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            abort(response()->json(['message' => 'Credenciales invalidas'], 401));
        }

        if (! $this->canLogin($user)) {
            abort(response()->json([
                'message' => 'Tu cuenta esta pendiente de aprobacion por un administrador.',
            ], 403));
        }

        return [
            'user' => $user,
            'token' => $user->createToken('API Token')->plainTextToken,
        ];
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
        ]);
    }

    public function updateProfile(User $user, array $data): User
    {
        $previousName = $user->name;

        if (! empty($data['new_password'])) {
            $this->validateCurrentPassword($user, $data['current_password'] ?? '');
            $data['password'] = Hash::make($data['new_password']);
        }

        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);
        $user->update($data);

        if ($this->isFamilyRole($user->role) && $previousName !== $user->name) {
            $this->updateFamilyCaregiverName($user, $previousName);
        }

        return $user->refresh();
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

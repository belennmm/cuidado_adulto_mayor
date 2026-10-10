<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public const CAREGIVER_ROLES = ['familiar', 'profesional'];

    public function all(): Collection
    {
        return User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved', 'location', 'phone', 'birthdate', 'created_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function approvedCaregivers(string $role): Collection
    {
        if (! in_array($role, self::CAREGIVER_ROLES, true)) {
            throw ValidationException::withMessages(['role' => ['El filtro de cuidador no es valido.']]);
        }

        return User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved')
            ->where('role', $role)
            ->where('is_approved', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): User
    {
        $data = $this->allowedData($data);
        $data['role'] = $this->normalizeRole($data['role']);
        $data['password'] = Hash::make($data['password']);
        $data['is_approved'] = true;

        $user = new User;
        $user->forceFill($data)->save();

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $data = $this->allowedData($data);
        $data['role'] = $this->normalizeRole($data['role']);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->forceFill($data)->save();

            return $user;
        });
    }

    public function approve(User $user): User
    {
        $user->forceFill(['is_approved' => true])->save();

        return $user;
    }

    public function reject(User $user): void
    {
        if ($user->role === 'admin' || $user->is_approved) {
            abort(response()->json([
                'message' => 'Solo se pueden rechazar solicitudes pendientes.',
            ], 422));
        }

        $this->delete($user);
    }

    public function delete(User $user): void
    {
        DB::transaction(fn () => $user->delete());
    }

    private function normalizeRole(string $role): string
    {
        if (! in_array($role, ['admin', 'familiar', 'profesional', 'cuidador_familiar', 'cuidador_profesional'], true)) {
            throw ValidationException::withMessages(['role' => ['El rol seleccionado no es valido.']]);
        }

        return match ($role) {
            'cuidador_profesional' => 'profesional',
            'cuidador_familiar' => 'familiar',
            default => $role,
        };
    }

    private function allowedData(array $data): array
    {
        return Arr::only($data, ['name', 'email', 'password', 'role', 'is_approved', 'location', 'phone', 'birthdate']);
    }
}

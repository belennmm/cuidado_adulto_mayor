<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class AdminUserService
{
    public function all(): Collection
    {
        return User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved', 'location', 'phone', 'birthdate', 'created_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function approvedCaregivers(string $role): Collection
    {
        return User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved')
            ->where('role', $role)
            ->where('is_approved', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): User
    {
        $data['role'] = $this->normalizeRole($data['role']);
        $data['password'] = Hash::make($data['password']);
        $data['is_approved'] = true;

        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $data['role'] = $this->normalizeRole($data['role']);

        if ($data['role'] === 'admin') {
            $data['is_approved'] = true;
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function approve(User $user): User
    {
        $user->update(['is_approved' => true]);

        return $user;
    }

    public function reject(User $user): void
    {
        if ($user->role === 'admin' || $user->is_approved) {
            abort(response()->json([
                'message' => 'Solo se pueden rechazar solicitudes pendientes.',
            ], 422));
        }

        $user->delete();
    }

    private function normalizeRole(string $role): string
    {
        return match ($role) {
            'cuidador_profesional' => 'profesional',
            'cuidador_familiar' => 'familiar',
            default => $role,
        };
    }
}

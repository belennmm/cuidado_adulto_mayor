<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $userRole = UserRole::fromValue($role);

        return User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved')
            ->whereIn('role', $userRole?->databaseValues() ?? [$role])
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
        $credentialsMustBeRevoked = ! empty($data['password'])
            || (array_key_exists('is_approved', $data) && ! $data['is_approved']);
        $data['role'] = $this->normalizeRole($data['role']);

        if ($data['role'] === UserRole::ADMIN->value) {
            $data['is_approved'] = true;
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($credentialsMustBeRevoked) {
            $this->revokeCredentials($user);
        }

        return $user;
    }

    public function approve(User $user): User
    {
        $user->update(['is_approved' => true]);

        return $user;
    }

    public function reject(User $user): void
    {
        if ($user->hasRole(UserRole::ADMIN) || $user->is_approved) {
            abort(response()->json([
                'message' => 'Solo se pueden rechazar solicitudes pendientes.',
            ], 422));
        }

        $this->delete($user);
    }

    public function delete(User $user): void
    {
        $this->revokeCredentials($user);
        $user->delete();
    }

    private function normalizeRole(string $role): string
    {
        return UserRole::fromValue($role)?->value ?? $role;
    }

    private function revokeCredentials(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }
}

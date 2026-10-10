<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Support\ListPage;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public const CAREGIVER_ROLES = ['familiar', 'profesional'];

    public function all(?array $input = null): Collection|LengthAwarePaginator
    {
        $query = User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved', 'location', 'phone', 'birthdate', 'created_at')
            ->orderByDesc('created_at')->orderByDesc('id');
        if (isset($input['is_approved'])) {
            $query->where('is_approved', filter_var($input['is_approved'], FILTER_VALIDATE_BOOLEAN));
        }
        $this->search($query, $input['q'] ?? null);

        return $input === null ? $query->get() : ListPage::query($query, $input);
    }

    public function approvedCaregivers(string $role, ?array $input = null): Collection|LengthAwarePaginator
    {
        if (! in_array($role, self::CAREGIVER_ROLES, true)) {
            throw ValidationException::withMessages(['role' => ['El filtro de cuidador no es valido.']]);
        }

        $userRole = UserRole::fromValue($role);

        $query = User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved')
            ->whereIn('role', $userRole?->databaseValues() ?? [$role])
            ->where('is_approved', true)
            ->orderBy('name')->orderBy('id');
        $this->search($query, $input['q'] ?? null);

        return $input === null ? $query->get() : ListPage::query($query, $input);
    }

    private function search($query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }
        $term = '%'.mb_strtolower($search).'%';
        $query->where(function ($query) use ($term, $search) {
            $query->whereRaw("LOWER(COALESCE(name, '')) LIKE ? OR LOWER(COALESCE(email, '')) LIKE ? OR LOWER(COALESCE(phone, '')) LIKE ? OR LOWER(COALESCE(role, '')) LIKE ?", [$term, $term, $term, $term]);
            $role = UserRole::fromValue(str_replace(' ', '_', mb_strtolower($search)));
            if ($role) {
                $query->orWhereIn('role', $role->databaseValues());
            }
            foreach (['admin' => 'administrador', 'profesional' => 'cuidador profesional', 'familiar' => 'cuidador familiar'] as $value => $label) {
                if (str_contains($label, mb_strtolower($search))) {
                    $query->orWhereIn('role', UserRole::fromValue($value)->databaseValues());
                }
            }
            foreach (['activo' => true, 'pendiente' => false] as $label => $approved) {
                if (str_contains($label, mb_strtolower($search))) {
                    $query->orWhere('is_approved', $approved);
                }
            }
        });
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
        if ($user->hasRole(UserRole::ADMIN) || $user->is_approved) {
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
        if (! in_array($role, UserRole::acceptedValues(), true)) {
            throw ValidationException::withMessages(['role' => ['El rol seleccionado no es valido.']]);
        }

        return UserRole::fromValue($role)->value;
    }

    private function allowedData(array $data): array
    {
        return Arr::only($data, ['name', 'email', 'password', 'role', 'is_approved', 'location', 'phone', 'birthdate']);
    }
}

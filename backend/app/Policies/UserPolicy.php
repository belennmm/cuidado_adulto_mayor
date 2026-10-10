<?php

namespace App\Policies;

use App\Models\User;
use App\Support\ResourceAccess as Access;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::admin($user);
    }

    public function view(User $user, User $subject): bool
    {
        return Access::admin($user);
    }

    public function create(User $user): bool
    {
        return Access::admin($user);
    }

    public function update(User $user, User $subject): bool
    {
        return Access::admin($user);
    }

    public function approve(User $user, User $subject): bool
    {
        return Access::admin($user);
    }

    public function reject(User $user, User $subject): bool
    {
        return Access::admin($user);
    }

    public function delete(User $user, User $subject): bool
    {
        return Access::admin($user);
    }
}

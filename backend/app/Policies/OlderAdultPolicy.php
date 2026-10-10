<?php

namespace App\Policies;

use App\Models\OlderAdult;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class OlderAdultPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::admin($user) || Access::caregiver($user);
    }

    public function view(User $user, OlderAdult $olderAdult): Response
    {
        return Access::admin($user) || Access::assigned($user, $olderAdult)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return Access::admin($user);
    }

    public function update(User $user, OlderAdult $olderAdult): bool
    {
        return Access::admin($user);
    }

    public function delete(User $user, OlderAdult $olderAdult): bool
    {
        return Access::admin($user);
    }
}

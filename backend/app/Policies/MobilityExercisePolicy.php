<?php

namespace App\Policies;

use App\Models\MobilityExercise;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class MobilityExercisePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::admin($user) || Access::professional($user);
    }

    public function view(User $user, MobilityExercise $exercise): Response
    {
        if (Access::admin($user) || (Access::professional($user) && $exercise->is_active)) {
            return Response::allow();
        }

        return Access::professional($user) ? Response::denyAsNotFound() : Response::deny();
    }

    public function create(User $user): bool
    {
        return Access::admin($user);
    }

    public function update(User $user, MobilityExercise $exercise): bool
    {
        return Access::admin($user);
    }

    public function delete(User $user, MobilityExercise $exercise): bool
    {
        return Access::admin($user);
    }
}

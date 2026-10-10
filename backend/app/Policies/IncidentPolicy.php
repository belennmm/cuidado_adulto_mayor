<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::admin($user) || Access::caregiver($user);
    }

    public function create(User $user): bool
    {
        return Access::admin($user) || Access::professional($user);
    }

    public function createFor(User $user, OlderAdult $olderAdult): Response
    {
        return Access::admin($user) || Access::professionalAssigned($user, $olderAdult)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Incident $incident): Response
    {
        return Access::professionalAssigned($user, $incident->olderAdult)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

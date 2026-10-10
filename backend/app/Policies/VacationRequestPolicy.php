<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VacationRequest;
use App\Support\ResourceAccess as Access;

class VacationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::professional($user);
    }

    public function view(User $user, VacationRequest $vacationRequest): bool
    {
        return Access::admin($user) || (Access::professional($user) && Access::owns($user, $vacationRequest->user_id));
    }

    public function create(User $user): bool
    {
        return Access::professional($user);
    }

    public function manage(User $user): bool
    {
        return Access::admin($user);
    }

    public function review(User $user, VacationRequest $vacationRequest): bool
    {
        return Access::admin($user);
    }
}

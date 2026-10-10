<?php

namespace App\Policies;

use App\Models\CaregiverSchedule;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class CaregiverSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::admin($user) || Access::professional($user);
    }

    public function view(User $user, CaregiverSchedule $schedule): bool
    {
        return Access::admin($user) || (Access::professional($user) && Access::owns($user, $schedule->user_id));
    }

    public function create(User $user): bool
    {
        return Access::professional($user);
    }

    public function manage(User $user): bool
    {
        return Access::admin($user);
    }

    public function update(User $user, CaregiverSchedule $schedule): Response
    {
        return $this->view($user, $schedule)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function requestChange(User $user, CaregiverSchedule $schedule): Response
    {
        return Access::professional($user) && Access::owns($user, $schedule->user_id)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function review(User $user, CaregiverSchedule $schedule): bool
    {
        return Access::admin($user);
    }

    public function delete(User $user, CaregiverSchedule $schedule): bool
    {
        return Access::admin($user);
    }
}

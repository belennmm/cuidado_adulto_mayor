<?php

namespace App\Policies;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class RoutineNotePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::professional($user);
    }

    public function create(User $user): bool
    {
        return Access::professional($user);
    }

    public function createFor(User $user, OlderAdult $olderAdult): Response
    {
        return Access::professionalAssigned($user, $olderAdult) ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, RoutineNote $note): Response
    {
        return Access::owns($user, $note->professional_caregiver_id)
            && Access::professionalAssigned($user, $note->olderAdult)
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function update(User $user, RoutineNote $note): Response
    {
        return $this->view($user, $note);
    }

    public function delete(User $user, RoutineNote $note): Response
    {
        return $this->view($user, $note);
    }
}

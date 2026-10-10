<?php

namespace App\Policies;

use App\Models\OlderAdult;
use App\Models\Rutina;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class RutinaPolicy
{
    public function viewAny(User $user): Response
    {
        return Access::admin($user) || Access::caregiver($user)
            ? Response::allow()
            : Response::deny('No tienes acceso a la informacion de rutinas.');
    }

    public function accessOlderAdult(User $user, OlderAdult $olderAdult): Response
    {
        if (Access::admin($user)) {
            return Response::allow();
        }

        if (! $this->isApprovedCaregiver($user)) {
            return Response::deny('Tu cuenta debe estar aprobada para crear rutinas.');
        }

        if ($this->isProfessional($user)) {
            return Access::professionalAssigned($user, $olderAdult)
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return $this->isFamily($user) && $this->isFamilyAssigned($user, $olderAdult)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Rutina $rutina): Response
    {
        if (Access::admin($user)) {
            return Response::allow();
        }

        return $rutina->olderAdult !== null
            ? $this->accessOlderAdult($user, $rutina->olderAdult)
            : Response::denyAsNotFound();
    }

    public function complete(User $user, Rutina $rutina): Response
    {
        return $this->update($user, $rutina);
    }

    public function delete(User $user, Rutina $rutina): Response
    {
        return $this->update($user, $rutina);
    }

    private function isApprovedCaregiver(User $user): bool
    {
        return Access::caregiver($user);
    }

    private function isProfessional(User $user): bool
    {
        return Access::professional($user);
    }

    private function isFamily(User $user): bool
    {
        return Access::family($user);
    }

    private function isFamilyAssigned(User $user, OlderAdult $olderAdult): bool
    {
        return Access::familyAssigned($user, $olderAdult);
    }

    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Rutina $rutina): Response
    {
        return $this->update($user, $rutina);
    }
}

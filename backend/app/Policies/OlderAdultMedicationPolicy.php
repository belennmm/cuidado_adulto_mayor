<?php

namespace App\Policies;

use App\Models\OlderAdultMedication;
use App\Models\User;
use App\Support\ResourceAccess as Access;
use Illuminate\Auth\Access\Response;

class OlderAdultMedicationPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::admin($user);
    }

    public function create(User $user): bool
    {
        return Access::admin($user);
    }

    public function update(User $user, OlderAdultMedication $item): bool
    {
        return Access::admin($user);
    }

    public function adjustStock(User $user, OlderAdultMedication $item): bool
    {
        return Access::admin($user);
    }

    public function delete(User $user, OlderAdultMedication $item): bool
    {
        return Access::admin($user);
    }

    public function markTaken(User $user, OlderAdultMedication $item): Response
    {
        return Access::professionalAssigned($user, $item->olderAdult) ? Response::allow() : Response::denyAsNotFound();
    }

    public function reminders(User $user): bool
    {
        return Access::professional($user);
    }
}

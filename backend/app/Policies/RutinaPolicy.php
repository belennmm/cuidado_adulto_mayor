<?php

namespace App\Policies;

use App\Models\OlderAdult;
use App\Models\Rutina;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

class RutinaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): Response
    {
        return $this->isApprovedCaregiver($user)
            ? Response::allow()
            : Response::deny('No tienes acceso a la informacion de rutinas.');
    }

    public function accessOlderAdult(User $user, OlderAdult $olderAdult): Response
    {
        if (! $this->isApprovedCaregiver($user)) {
            return Response::deny('Tu cuenta debe estar aprobada para crear rutinas.');
        }

        if ($this->isProfessional($user)) {
            return (int) $olderAdult->professional_caregiver_id === (int) $user->id
                ? Response::allow()
                : Response::deny('No tienes acceso a la informacion de este adulto mayor.');
        }

        return $this->isFamily($user) && $this->isFamilyAssigned($user, $olderAdult)
            ? Response::allow()
            : Response::deny('No tienes acceso a la informacion de este adulto mayor.');
    }

    public function update(User $user, Rutina $rutina): Response
    {
        return $rutina->olderAdult !== null
            ? $this->accessOlderAdult($user, $rutina->olderAdult)
            : Response::deny('No tienes acceso a la informacion de este adulto mayor.');
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
        return (bool) $user->is_approved && ($this->isProfessional($user) || $this->isFamily($user));
    }

    private function isProfessional(User $user): bool
    {
        return in_array($this->role($user), ['profesional', 'cuidador_profesional'], true);
    }

    private function isFamily(User $user): bool
    {
        return in_array($this->role($user), ['familiar', 'cuidador_familiar'], true);
    }

    private function isAdmin(User $user): bool
    {
        return in_array($this->role($user), ['admin', 'administrador'], true);
    }

    private function isFamilyAssigned(User $user, OlderAdult $olderAdult): bool
    {
        if ((int) $olderAdult->family_caregiver_id === (int) $user->id) {
            return true;
        }

        return $olderAdult->family_caregiver_id === null
            && $this->normalize($olderAdult->caregiver_family) === $this->normalize($user->name);
    }

    private function role(User $user): string
    {
        return $this->normalize($user->role);
    }

    private function normalize(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }
}

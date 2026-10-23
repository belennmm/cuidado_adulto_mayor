<?php

namespace App\Authorization;

use App\Enums\UserRole;
use App\Models\User;

final class IncidentListingAccess
{
    public function assertAllowed(User $user): UserRole
    {
        $role = $user->roleEnum();
        if ($role === null) {
            abort(response()->json(['message' => 'No tienes acceso para consultar incidentes.'], 403));
        }
        if (! $user->is_approved) {
            abort(response()->json(['message' => 'Tu cuenta debe estar aprobada para consultar incidentes.'], 403));
        }

        return $role;
    }
}

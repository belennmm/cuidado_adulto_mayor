<?php

namespace App\Queries;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class IncidentListingQuery
{
    /** El servicio verifica rol/aprobación antes de construir esta consulta. */
    public function forDate(User $user, UserRole $role, string $date): Builder
    {
        $query = Incident::query()->with([
            'reporter:id,name,email',
            'olderAdult.familyCaregiver:id,name,email',
            'olderAdult.professionalCaregiver:id,name,email',
        ]);
        $assignmentColumn = match ($role) {
            UserRole::FAMILY => 'family_caregiver_id',
            UserRole::PROFESSIONAL => 'professional_caregiver_id',
            UserRole::ADMIN => null,
        };
        if ($assignmentColumn !== null) {
            $adultIds = OlderAdult::query()->where($assignmentColumn, $user->id)
                ->get(['id', 'full_name'])->pluck('id')->filter()->values();
            if ($adultIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('older_adult_id', $adultIds);
            }
        }

        return $query->whereDate('incident_date', $date)
            ->orderByRaw('incident_time IS NULL')->orderBy('incident_time')
            ->orderByDesc('created_at')->orderByDesc('id');
    }
}

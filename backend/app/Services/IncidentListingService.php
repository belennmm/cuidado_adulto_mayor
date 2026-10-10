<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IncidentListingService
{
    public function forDate(User $user, string $date): Collection
    {
        $query = Incident::query()->with([
            'reporter:id,name,email',
            'olderAdult.familyCaregiver:id,name,email',
            'olderAdult.professionalCaregiver:id,name,email',
        ]);

        $this->scopeForUser($query, $user);

        return $query
            ->whereDate('incident_date', $date)
            ->orderByRaw('incident_time IS NULL')
            ->orderBy('incident_time')
            ->orderByDesc('created_at')
            ->get();
    }

    private function scopeForUser(Builder $query, User $user): void
    {
        $role = $user->roleEnum();

        if (! in_array($role, UserRole::cases(), true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para consultar incidentes.',
            ], 403));
        }

        if (in_array($role, UserRole::cases(), true)
            && ! $user->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para consultar incidentes.',
            ], 403));
        }

        if ($role === UserRole::FAMILY) {
            $olderAdults = OlderAdult::query()
                ->where('family_caregiver_id', $user->id)
                ->get(['id', 'full_name']);

            $this->scopeToOlderAdults($query, $olderAdults);

            return;
        }

        if ($role === UserRole::PROFESSIONAL) {
            $olderAdults = OlderAdult::query()
                ->where('professional_caregiver_id', $user->id)
                ->get(['id', 'full_name']);

            $this->scopeToOlderAdults($query, $olderAdults);
        }
    }

    private function scopeToOlderAdults(Builder $query, Collection $olderAdults): void
    {
        $adultIds = $olderAdults->pluck('id')->filter()->values();
        if ($adultIds->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('older_adult_id', $adultIds);
    }
}

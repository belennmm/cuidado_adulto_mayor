<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

        if (in_array($role, [UserRole::FAMILY, UserRole::PROFESSIONAL], true)
            && ! $user->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para consultar incidentes.',
            ], 403));
        }

        if ($role === UserRole::FAMILY) {
            $olderAdults = OlderAdult::query()
                ->where(function (Builder $assignedQuery) use ($user) {
                    $assignedQuery
                        ->where('family_caregiver_id', $user->id)
                        ->orWhere(function (Builder $legacyQuery) use ($user) {
                            $legacyQuery->whereNull('family_caregiver_id')
                                ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $user->name)]);
                        });
                })
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
        $adultNames = $olderAdults->pluck('full_name')->filter()->values();

        if ($adultIds->isEmpty() && $adultNames->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $incidentQuery) use ($adultIds, $adultNames) {
            $incidentQuery->whereIn('older_adult_id', $adultIds);

            if ($adultNames->isNotEmpty()) {
                $incidentQuery->orWhere(function (Builder $legacyQuery) use ($adultNames) {
                    $legacyQuery->whereNull('older_adult_id')->whereIn('adult_name', $adultNames);
                });
            }
        });
    }

}

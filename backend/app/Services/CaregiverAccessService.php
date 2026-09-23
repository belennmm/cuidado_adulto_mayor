<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CaregiverAccessService
{
    public const FAMILY = 'family';

    public const PROFESSIONAL = 'professional';

    public function __construct(private readonly CareDataService $careData) {}

    public function authorize(User $user, string $caregiverType): void
    {
        $role = $this->normalizeText($user->role);
        $allowedRoles = $caregiverType === self::FAMILY
            ? ['familiar', 'cuidador_familiar']
            : ['profesional', 'cuidador_profesional'];

        if (in_array($role, $allowedRoles, true) && (bool) $user->is_approved) {
            return;
        }

        $message = $caregiverType === self::FAMILY
            ? 'Esta informacion solo esta disponible para cuidadores familiares.'
            : 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.';

        abort(response()->json(['message' => $message], 403));
    }

    public function assignedOlderAdults(User $user, string $caregiverType): Builder
    {
        $query = OlderAdult::query()
            ->with([
                'medicationAssignments.medication',
                'familyCaregiver:id,name,email,phone,location',
                'professionalCaregiver:id,name,email,phone,location',
            ]);

        if ($caregiverType === self::PROFESSIONAL) {
            return $query
                ->where('professional_caregiver_id', $user->id)
                ->orderBy('full_name');
        }

        return $query
            ->where(function (Builder $assignedQuery) use ($user) {
                $assignedQuery
                    ->where('family_caregiver_id', $user->id)
                    ->orWhere(function (Builder $legacyQuery) use ($user) {
                        $legacyQuery
                            ->whereNull('family_caregiver_id')
                            ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $user->name)]);
                    });
            })
            ->orderBy('full_name');
    }

    public function olderAdultsFor(User $user, string $caregiverType, mixed $olderAdultId = null): Collection
    {
        if ($olderAdultId !== null && $olderAdultId !== '') {
            return collect([$this->assignedOlderAdultOrFail($user, $caregiverType, (int) $olderAdultId)]);
        }

        return $this->assignedOlderAdults($user, $caregiverType)->get();
    }

    public function assignedOlderAdultOrFail(User $user, string $caregiverType, int $olderAdultId): OlderAdult
    {
        $olderAdult = $this->assignedOlderAdults($user, $caregiverType)
            ->whereKey($olderAdultId)
            ->first();

        if ($olderAdult) {
            return $olderAdult;
        }

        abort(response()->json([
            'message' => 'No tienes acceso a la informacion de este adulto mayor.',
        ], 403));
    }

    public function incidentsFor(Collection $olderAdults, ?Carbon $date = null, bool $includeLegacy = false): Collection
    {
        $adultIds = $olderAdults->pluck('id')->filter()->values();
        $adultNames = $olderAdults->pluck('full_name')->filter()->values();

        if ($adultIds->isEmpty() && (! $includeLegacy || $adultNames->isEmpty())) {
            return collect();
        }

        $query = Incident::query()
            ->with([
                'reporter:id,name,email',
                'olderAdult.familyCaregiver:id,name,email,phone,location',
                'olderAdult.professionalCaregiver:id,name,email,phone,location',
                'olderAdult.medicationAssignments.medication',
            ])
            ->where(function (Builder $incidentQuery) use ($adultIds, $adultNames, $includeLegacy) {
                $incidentQuery->whereIn('older_adult_id', $adultIds);

                if ($includeLegacy && $adultNames->isNotEmpty()) {
                    $incidentQuery->orWhere(function (Builder $legacyQuery) use ($adultNames) {
                        $legacyQuery->whereNull('older_adult_id')->whereIn('adult_name', $adultNames);
                    });
                }
            });

        if ($date) {
            $query->whereDate('incident_date', $date->toDateString());
        }

        return $query
            ->orderByDesc('incident_date')
            ->orderByRaw('incident_time IS NULL')
            ->orderByDesc('incident_time')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Incident $incident) => $this->formatIncident($incident, $olderAdults, $includeLegacy))
            ->values();
    }

    public function incidentSummary(Collection $incidents): array
    {
        return [
            'total' => $incidents->count(),
            'open' => $incidents
                ->filter(fn (array $incident) => ! $this->isResolved($incident['status']))
                ->count(),
            'resolved' => $incidents
                ->filter(fn (array $incident) => $this->isResolved($incident['status']))
                ->count(),
        ];
    }

    private function formatIncident(Incident $incident, Collection $olderAdults, bool $includeReporter): array
    {
        $olderAdult = $incident->olderAdult
            ?? $olderAdults->first(fn (OlderAdult $adult) => $this->normalizeText($adult->full_name) === $this->normalizeText($incident->adult_name));

        $formatted = [
            'id' => $incident->id,
            'title' => $incident->title,
            'description' => $incident->description,
            'adult_name' => $incident->adult_name ?? $olderAdult?->full_name,
            'older_adult_id' => $incident->older_adult_id ?? $olderAdult?->id,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'incident_date' => $incident->incident_date?->toDateString(),
            'incident_time' => $incident->incident_time,
            'reported_by' => $incident->reporter?->name,
            'older_adult' => $olderAdult ? $this->olderAdultSummary($olderAdult) : null,
        ];

        if ($includeReporter) {
            $formatted['reporter'] = $incident->reporter ? [
                'id' => $incident->reporter->id,
                'name' => $incident->reporter->name,
                'email' => $incident->reporter->email,
            ] : null;
        }

        return $formatted;
    }

    private function olderAdultSummary(OlderAdult $olderAdult): array
    {
        return $this->careData->formatOlderAdultSummary($olderAdult);
    }

    private function isResolved(mixed $status): bool
    {
        return in_array($this->normalizeText($status), ['cerrado', 'resuelto'], true);
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->toString();
    }
}

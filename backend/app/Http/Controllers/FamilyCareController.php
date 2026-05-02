<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FamilyCareController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $today = $this->today();
        $olderAdults = $this->assignedOlderAdults($request->user())->get();
        $routineToday = $this->buildRoutine($olderAdults, $today, true);
        $incidentsToday = $this->todayIncidents($olderAdults, $today);
        $statusSummary = $this->statusSummary($olderAdults);

        return response()->json([
            'date' => $today->toDateString(),
            'user' => $this->formatUser($request->user()),
            'stats' => [
                'older_adults' => $olderAdults->count(),
                'stable' => $statusSummary['stable'],
                'attention' => $statusSummary['attention'],
                'critical' => $statusSummary['critical'],
                'medications_today' => $routineToday->count(),
                'pending_medications' => $routineToday->where('administered_today', false)->count(),
                'incidents_today' => $incidentsToday->count(),
            ],
            'older_adults' => $olderAdults
                ->take(3)
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultSummary($olderAdult))
                ->values(),
            'next_medications' => $routineToday
                ->sortBy('sort_order')
                ->take(4)
                ->values()
                ->map(fn (array $entry) => $this->withoutSortOrder($entry)),
            'incidents' => $incidentsToday->take(4)->values(),
        ]);
    }

    public function olderAdults(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $today = $this->today();
        $olderAdults = $this->assignedOlderAdults($request->user())->get();
        $administeredMap = $this->administeredMedicationMap(
            $this->medicationAssignmentIds($olderAdults),
            $today
        );

        return response()->json([
            'date' => $today->toDateString(),
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultDetail($olderAdult, $today, $administeredMap))
                ->values(),
        ]);
    }

    public function olderAdult(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $today = $this->today();
        $assignedOlderAdult = $this->assignedOlderAdultOrFail($request->user(), $olderAdult->id);
        $olderAdults = collect([$assignedOlderAdult]);
        $administeredMap = $this->administeredMedicationMap(
            $this->medicationAssignmentIds($olderAdults),
            $today
        );
        $incidents = $this->incidentsForOlderAdults($olderAdults);

        return response()->json([
            'date' => $today->toDateString(),
            'older_adult' => [
                ...$this->formatOlderAdultDetail($assignedOlderAdult, $today, $administeredMap),
                'incidents_count' => $incidents->count(),
                'last_incident' => $incidents->first(),
                'incidents' => $incidents,
            ],
        ]);
    }

    public function routine(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $data = $request->validate([
            'older_adult_id' => 'nullable|integer',
        ]);

        $today = $this->today();
        $olderAdults = $this->olderAdultsForFamilyRequest($request, $data['older_adult_id'] ?? null);
        $routine = $this->buildRoutine($olderAdults, $today, false);
        $todayRoutine = $routine->where('due_today', true);

        return response()->json([
            'date' => $today->toDateString(),
            'summary' => [
                'total' => $routine->count(),
                'today' => $todayRoutine->count(),
                'administered_today' => $todayRoutine->where('administered_today', true)->count(),
                'pending_today' => $todayRoutine->where('administered_today', false)->count(),
            ],
            'routine' => $routine
                ->sortBy([
                    ['due_today', 'desc'],
                    ['sort_order', 'asc'],
                    ['older_adult_name', 'asc'],
                ])
                ->values()
                ->map(fn (array $entry) => $this->withoutSortOrder($entry)),
        ]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $this->ensureFamilyUser($request);

        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'older_adult_id' => 'nullable|integer',
        ]);

        $date = isset($data['date'])
            ? Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay()
            : $this->today();

        $olderAdults = $this->olderAdultsForFamilyRequest($request, $data['older_adult_id'] ?? null);
        $incidents = $this->incidentsForOlderAdults($olderAdults, $date);

        return response()->json([
            'date' => $date->toDateString(),
            'summary' => [
                'total' => $incidents->count(),
                'open' => $incidents
                    ->filter(fn (array $incident) => !in_array($this->normalizeText($incident['status']), ['cerrado', 'resuelto'], true))
                    ->count(),
                'resolved' => $incidents
                    ->filter(fn (array $incident) => in_array($this->normalizeText($incident['status']), ['cerrado', 'resuelto'], true))
                    ->count(),
            ],
            'older_adults' => $olderAdults
                ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdultSummary($olderAdult))
                ->values(),
            'incidents' => $incidents,
        ]);
    }

    private function ensureFamilyUser(Request $request): void
    {
        $user = $request->user();
        $role = $this->normalizeText($user?->role);

        if (($role === 'familiar' || $role === 'cuidador_familiar') && (bool) $user?->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'Esta informacion solo esta disponible para cuidadores familiares.',
        ], 403));
    }

    private function olderAdultsForFamilyRequest(Request $request, mixed $olderAdultId = null): Collection
    {
        if ($olderAdultId !== null && $olderAdultId !== '') {
            return collect([$this->assignedOlderAdultOrFail($request->user(), (int) $olderAdultId)]);
        }

        return $this->assignedOlderAdults($request->user())->get();
    }

    private function assignedOlderAdultOrFail(User $user, int $olderAdultId): OlderAdult
    {
        $olderAdult = $this->assignedOlderAdults($user)
            ->whereKey($olderAdultId)
            ->first();

        if ($olderAdult) {
            return $olderAdult;
        }

        abort(response()->json([
            'message' => 'No tienes acceso a la informacion de este adulto mayor.',
        ], 403));
    }

    private function assignedOlderAdults(User $user)
    {
        return OlderAdult::query()
            ->with([
                'medicationAssignments.medication',
                'familyCaregiver:id,name,email,phone,location',
                'professionalCaregiver:id,name,email,phone,location',
            ])
            ->where(function ($query) use ($user) {
                $query
                    ->where('family_caregiver_id', $user->id)
                    ->orWhere(function ($legacyQuery) use ($user) {
                        $legacyQuery
                            ->whereNull('family_caregiver_id')
                            ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $user->name)]);
                    });
            })
            ->orderBy('full_name');
    }

    private function buildRoutine(Collection $olderAdults, Carbon $today, bool $onlyDueToday): Collection
    {
        $assignmentIds = $this->medicationAssignmentIds($olderAdults);
        $administeredMap = $this->administeredMedicationMap($assignmentIds, $today);

        return $olderAdults
            ->flatMap(function (OlderAdult $olderAdult) use ($today, $onlyDueToday, $administeredMap) {
                return $olderAdult->medicationAssignments
                    ->filter(fn ($assignment) => (bool) $assignment->is_active)
                    ->map(function ($assignment) use ($olderAdult, $today, $administeredMap) {
                        $dueToday = $this->isDueToday($assignment->days, $today);
                        $administeredTime = $administeredMap->get($assignment->id);

                        return [
                            'id' => $assignment->id,
                            'older_adult_id' => $olderAdult->id,
                            'older_adult_name' => $olderAdult->full_name,
                            'room' => $olderAdult->room,
                            'status' => $olderAdult->status,
                            'medication_name' => $assignment->medication?->name,
                            'dosage' => $assignment->dosage,
                            'schedule' => $assignment->schedule,
                            'days' => $assignment->days ?? [],
                            'notes' => $assignment->notes,
                            'due_today' => $dueToday,
                            'administered_today' => $administeredTime !== null,
                            'administered_time' => $administeredTime,
                            'sort_order' => $this->scheduleSortOrder($assignment->schedule),
                        ];
                    })
                    ->filter(fn (array $entry) => !$onlyDueToday || $entry['due_today']);
            })
            ->values();
    }

    private function todayIncidents(Collection $olderAdults, Carbon $today): Collection
    {
        return $this->incidentsForOlderAdults($olderAdults, $today);
    }

    private function incidentsForOlderAdults(Collection $olderAdults, ?Carbon $date = null): Collection
    {
        $adultIds = $olderAdults->pluck('id')->filter()->values();
        $adultNames = $olderAdults->pluck('full_name')->filter()->values();

        if ($adultIds->isEmpty() && $adultNames->isEmpty()) {
            return collect();
        }

        $query = Incident::query()
            ->with([
                'reporter:id,name,email',
                'olderAdult.familyCaregiver:id,name,email,phone,location',
                'olderAdult.professionalCaregiver:id,name,email,phone,location',
                'olderAdult.medicationAssignments.medication',
            ])
            ->where(function ($incidentQuery) use ($adultIds, $adultNames) {
                $incidentQuery->whereIn('older_adult_id', $adultIds);

                if ($adultNames->isNotEmpty()) {
                    $incidentQuery->orWhere(function ($legacyQuery) use ($adultNames) {
                        $legacyQuery
                            ->whereNull('older_adult_id')
                            ->whereIn('adult_name', $adultNames);
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
            ->map(fn (Incident $incident) => $this->formatIncident($incident, $olderAdults))
            ->values();
    }

    private function formatIncident(Incident $incident, Collection $olderAdults): array
    {
        $olderAdult = $incident->olderAdult
            ?? $olderAdults->first(
                fn (OlderAdult $adult) => $this->normalizeText($adult->full_name) === $this->normalizeText($incident->adult_name)
            );

        return [
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
            'reporter' => $incident->reporter ? [
                'id' => $incident->reporter->id,
                'name' => $incident->reporter->name,
                'email' => $incident->reporter->email,
            ] : null,
            'older_adult' => $olderAdult ? $this->formatOlderAdultSummary($olderAdult) : null,
        ];
    }

    private function statusSummary(Collection $olderAdults): array
    {
        $summary = [
            'stable' => 0,
            'attention' => 0,
            'critical' => 0,
        ];

        foreach ($olderAdults as $olderAdult) {
            $summary[$this->statusKey($olderAdult->status)]++;
        }

        return $summary;
    }

    private function formatOlderAdultSummary(OlderAdult $olderAdult): array
    {
        return [
            'id' => $olderAdult->id,
            'full_name' => $olderAdult->full_name,
            'age' => $olderAdult->age,
            'room' => $olderAdult->room,
            'status' => $olderAdult->status,
            'family_caregiver_id' => $olderAdult->family_caregiver_id,
            'family_caregiver_name' => $olderAdult->familyCaregiver?->name ?? $olderAdult->caregiver_family,
            'family_caregiver' => $this->formatCaregiver($olderAdult->familyCaregiver),
            'professional_caregiver_id' => $olderAdult->professional_caregiver_id,
            'professional_caregiver_name' => $olderAdult->professionalCaregiver?->name,
            'professional_caregiver' => $this->formatCaregiver($olderAdult->professionalCaregiver),
            'medications_count' => $olderAdult->medicationAssignments
                ->where('is_active', true)
                ->count(),
        ];
    }

    private function formatOlderAdultDetail(OlderAdult $olderAdult, Carbon $today, Collection $administeredMap): array
    {
        return [
            ...$this->formatOlderAdultSummary($olderAdult),
            'birthdate' => $olderAdult->birthdate?->toDateString(),
            'gender' => $olderAdult->gender,
            'caregiver_family' => $olderAdult->caregiver_family,
            'emergency_contact_name' => $olderAdult->emergency_contact_name,
            'emergency_contact_phone' => $olderAdult->emergency_contact_phone,
            'allergies' => $olderAdult->allergies,
            'medical_history' => $olderAdult->medical_history,
            'notes' => $olderAdult->notes,
            'created_at' => $olderAdult->created_at?->toISOString(),
            'updated_at' => $olderAdult->updated_at?->toISOString(),
            'medications' => $olderAdult->medicationAssignments
                ->filter(fn ($assignment) => (bool) $assignment->is_active)
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'medication_id' => $assignment->medication_id,
                    'name' => $assignment->medication?->name,
                    'dosage' => $assignment->dosage,
                    'schedule' => $assignment->schedule,
                    'days' => $assignment->days ?? [],
                    'notes' => $assignment->notes,
                    'start_date' => $assignment->start_date?->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                    'is_active' => $assignment->is_active,
                    'due_today' => $this->isDueToday($assignment->days, $today),
                    'administered_today' => $administeredMap->has($assignment->id),
                    'administered_time' => $administeredMap->get($assignment->id),
                ])
                ->values(),
        ];
    }

    private function formatCaregiver(?User $caregiver): ?array
    {
        if (!$caregiver) {
            return null;
        }

        return [
            'id' => $caregiver->id,
            'name' => $caregiver->name,
            'email' => $caregiver->email,
            'phone' => $caregiver->phone,
            'location' => $caregiver->location,
        ];
    }

    private function medicationAssignmentIds(Collection $olderAdults): Collection
    {
        return $olderAdults
            ->flatMap(fn (OlderAdult $olderAdult) => $olderAdult->medicationAssignments->pluck('id'))
            ->filter()
            ->values();
    }

    private function administeredMedicationMap(Collection $assignmentIds, Carbon $today): Collection
    {
        if ($assignmentIds->isEmpty()) {
            return collect();
        }

        return MedicationAdministration::query()
            ->where('administration_type', 'scheduled')
            ->whereDate('administration_date', $today->toDateString())
            ->whereIn('older_adult_medication_id', $assignmentIds)
            ->whereNotNull('older_adult_medication_id')
            ->orderBy('administration_time')
            ->get()
            ->pluck('administration_time', 'older_adult_medication_id');
    }

    private function isDueToday(mixed $days, Carbon $today): bool
    {
        if (!is_array($days) || count($days) === 0) {
            return true;
        }

        $todayName = $this->normalizeText($this->spanishDayName($today));

        return collect($days)
            ->map(fn ($day) => $this->normalizeText($day))
            ->contains($todayName);
    }

    private function spanishDayName(Carbon $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            default => 'domingo',
        };
    }

    private function statusKey(?string $status): string
    {
        return match ($this->normalizeText($status)) {
            'estable' => 'stable',
            'atencion' => 'attention',
            default => 'critical',
        };
    }

    private function scheduleSortOrder(?string $schedule): int
    {
        if (!preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', (string) $schedule, $matches)) {
            return 9999;
        }

        $hour = (int) $matches[1];
        $minutes = (int) $matches[2];
        $period = strtoupper($matches[3] ?? '');

        if ($period === 'PM' && $hour < 12) {
            $hour += 12;
        }

        if ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }

        return ($hour * 60) + $minutes;
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }

    private function withoutSortOrder(array $entry): array
    {
        unset($entry['sort_order']);

        return $entry;
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_approved' => $user->is_approved,
            'location' => $user->location,
            'phone' => $user->phone,
            'birthdate' => $user->birthdate?->toDateString(),
        ];
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }
}

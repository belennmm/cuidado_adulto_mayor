<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\OlderAdult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = (string) config('app.timezone');

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'], $timezone)->startOfDay()->toDateString()
            : Carbon::now($timezone)->startOfDay()->toDateString();

        return $this->respondWithIncidentsForDate($request, $date);
    }

    public function today(Request $request): JsonResponse
    {
        $timezone = (string) config('app.timezone');
        $date = Carbon::now($timezone)->startOfDay()->toDateString();

        return $this->respondWithIncidentsForDate($request, $date);
    }

    private function respondWithIncidentsForDate(Request $request, string $date): JsonResponse
    {
        $query = Incident::query()
            ->with([
                'reporter:id,name,email',
                'olderAdult.familyCaregiver:id,name,email',
                'olderAdult.professionalCaregiver:id,name,email',
            ]);

        $this->scopeIncidentsForUser($query, $request);

        $incidents = $query
            ->whereDate('incident_date', $date)
            ->orderByRaw('incident_time IS NULL')
            ->orderBy('incident_time')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Incident $incident) => $this->formatIncident($incident));

        return response()->json([
            'date' => $date,
            'incidents' => $incidents,
        ]);
    }

    private function scopeIncidentsForUser($query, Request $request): void
    {
        $user = $request->user();
        $role = $this->normalizeRole($user?->role);

        if (in_array($role, ['familiar', 'cuidador_familiar', 'profesional', 'cuidador_profesional'], true) && !(bool) $user?->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para consultar incidentes.',
            ], 403));
        }

        if ($role === 'familiar' || $role === 'cuidador_familiar') {
            $olderAdults = OlderAdult::query()
                ->where(function ($query) use ($user) {
                    $query
                        ->where('family_caregiver_id', $user->id)
                        ->orWhere(function ($legacyQuery) use ($user) {
                            $legacyQuery
                                ->whereNull('family_caregiver_id')
                                ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $user->name)]);
                        });
                })
                ->get(['id', 'full_name']);

            $this->scopeQueryToOlderAdults($query, $olderAdults);
            return;
        }

        if ($role === 'profesional' || $role === 'cuidador_profesional') {
            $olderAdults = OlderAdult::query()
                ->where('professional_caregiver_id', $user->id)
                ->get(['id', 'full_name']);

            $this->scopeQueryToOlderAdults($query, $olderAdults);
        }
    }

    private function scopeQueryToOlderAdults($query, $olderAdults): void
    {
        $adultIds = $olderAdults->pluck('id')->filter()->values();
        $adultNames = $olderAdults->pluck('full_name')->filter()->values();

        if ($adultIds->isEmpty() && $adultNames->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($incidentQuery) use ($adultIds, $adultNames) {
            $incidentQuery->whereIn('older_adult_id', $adultIds);

            if ($adultNames->isNotEmpty()) {
                $incidentQuery->orWhere(function ($legacyQuery) use ($adultNames) {
                    $legacyQuery
                        ->whereNull('older_adult_id')
                        ->whereIn('adult_name', $adultNames);
                });
            }
        });
    }

    private function formatIncident(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'description' => $incident->description,
            'adult_name' => $incident->adult_name ?? $incident->olderAdult?->full_name,
            'older_adult_id' => $incident->older_adult_id ?? $incident->olderAdult?->id,
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
            'older_adult' => $incident->olderAdult ? [
                'id' => $incident->olderAdult->id,
                'full_name' => $incident->olderAdult->full_name,
                'age' => $incident->olderAdult->age,
                'room' => $incident->olderAdult->room,
                'status' => $incident->olderAdult->status,
                'family_caregiver_id' => $incident->olderAdult->family_caregiver_id,
                'family_caregiver_name' => $incident->olderAdult->familyCaregiver?->name ?? $incident->olderAdult->caregiver_family,
                'professional_caregiver_name' => $incident->olderAdult->professionalCaregiver?->name,
            ] : null,
        ];
    }

    private function normalizeRole(mixed $role): string
    {
        return Str::of((string) $role)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }
}

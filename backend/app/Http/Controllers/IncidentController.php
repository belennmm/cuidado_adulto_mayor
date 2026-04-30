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
    public function today(Request $request): JsonResponse
    {
        $date = Carbon::now(config('app.timezone'))->startOfDay()->toDateString();

        $query = Incident::query()
            ->with('reporter:id,name,email');

        $this->scopeIncidentsForUser($query, $request);

        $incidents = $query
            ->whereDate('incident_date', $date)
            ->orderByRaw('incident_time IS NULL')
            ->orderBy('incident_time')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'title' => $incident->title,
                'description' => $incident->description,
                'adult_name' => $incident->adult_name,
                'severity' => $incident->severity,
                'status' => $incident->status,
                'incident_date' => $incident->incident_date?->toDateString(),
                'incident_time' => $incident->incident_time,
                'reported_by' => $incident->reporter?->name,
            ]);

        return response()->json([
            'date' => $date,
            'incidents' => $incidents,
        ]);
    }

    private function scopeIncidentsForUser($query, Request $request): void
    {
        $user = $request->user();
        $role = $this->normalizeRole($user?->role);

        if ($role === 'familiar' || $role === 'cuidador_familiar') {
            $adultNames = OlderAdult::query()
                ->where(function ($query) use ($user) {
                    $query
                        ->where('family_caregiver_id', $user->id)
                        ->orWhere(function ($legacyQuery) use ($user) {
                            $legacyQuery
                                ->whereNull('family_caregiver_id')
                                ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $user->name)]);
                        });
                })
                ->pluck('full_name');

            $query->whereIn('adult_name', $adultNames);
            return;
        }

        if ($role === 'profesional' || $role === 'cuidador_profesional') {
            $adultNames = OlderAdult::query()
                ->where('professional_caregiver_id', $user->id)
                ->pluck('full_name');

            $query->whereIn('adult_name', $adultNames);
        }
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

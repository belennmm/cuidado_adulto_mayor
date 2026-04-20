<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IncidentController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $date = Carbon::today()->toDateString();

        $incidents = Incident::query()
            ->with('reporter:id,name,email')
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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\DateFilterRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Services\IncidentListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentListingService $incidentListing) {}

    public function index(DateFilterRequest $request): JsonResponse
    {
        $date = $this->date($request->validated('date'));

        return $this->incidentsResponse($request, $date);
    }

    public function today(Request $request): JsonResponse
    {
        return $this->incidentsResponse($request, $this->date());
    }

    private function incidentsResponse(Request $request, string $date): JsonResponse
    {
        $incidents = $this->incidentListing->forDate($request->user(), $date);

        return response()->json([
            'date' => $date,
            'incidents' => $incidents
                ->map(fn (Incident $incident) => IncidentResource::make($incident)->toArray($request))
                ->values(),
        ]);
    }

    private function date(?string $date = null): string
    {
        $timezone = (string) config('app.timezone');

        return $date
            ? Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->toDateString()
            : Carbon::now($timezone)->startOfDay()->toDateString();
    }
}

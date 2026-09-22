<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicationStatisticsRequest;
use App\Services\AdminDashboardService;
use App\Services\MedicationStatisticsService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
        private readonly MedicationStatisticsService $medicationStatisticsService,
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->dashboardService->summary());
    }

    public function medicationStatistics(MedicationStatisticsRequest $request): JsonResponse
    {
        $filter = $request->validated('filter', 'day');

        return response()->json(
            $this->medicationStatisticsService->statistics($filter),
        );
    }
}

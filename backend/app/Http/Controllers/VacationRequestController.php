<?php

namespace App\Http\Controllers;

use App\Http\Requests\VacationStoreRequest;
use App\Http\Resources\VacationRequestResource;
use App\Models\VacationRequest;
use App\Services\VacationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    public function __construct(private readonly VacationRequestService $vacationService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->collectionResponse(
            $this->vacationService->forProfessional($request->user()),
            $request,
        );
    }

    public function store(VacationStoreRequest $request): JsonResponse
    {
        $vacationRequest = $this->vacationService->create(
            $request->user(), $request->validated(),
        );

        return $this->requestResponse(
            'Solicitud de vacaciones enviada correctamente.', $vacationRequest, $request, 201,
        );
    }

    public function adminIndex(Request $request): JsonResponse
    {
        return $this->collectionResponse($this->vacationService->allForAdmin(), $request);
    }

    public function approve(Request $request, VacationRequest $vacationRequest): JsonResponse
    {
        $vacationRequest = $this->vacationService->review(
            $vacationRequest, $request->user(), 'approved',
        );

        return $this->requestResponse(
            'Solicitud de vacaciones aprobada.', $vacationRequest, $request,
        );
    }

    public function reject(Request $request, VacationRequest $vacationRequest): JsonResponse
    {
        $vacationRequest = $this->vacationService->review(
            $vacationRequest, $request->user(), 'rejected',
        );

        return $this->requestResponse(
            'Solicitud de vacaciones rechazada.', $vacationRequest, $request,
        );
    }

    private function collectionResponse($vacationRequests, Request $request): JsonResponse
    {
        return response()->json([
            'vacation_requests' => $vacationRequests
                ->map(fn (VacationRequest $item) => $this->resource($item, $request))
                ->values(),
        ]);
    }

    private function requestResponse(
        string $message,
        VacationRequest $vacationRequest,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'vacation_request' => $this->resource($vacationRequest, $request),
        ], $status);
    }

    private function resource(VacationRequest $vacationRequest, Request $request): array
    {
        return VacationRequestResource::make($vacationRequest)->toArray($request);
    }
}

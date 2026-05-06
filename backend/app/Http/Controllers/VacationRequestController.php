<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VacationRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureProfessionalUser($request->user());

        return response()->json([
            'vacation_requests' => VacationRequest::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (VacationRequest $vacationRequest) => $this->formatVacationRequest($vacationRequest))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureProfessionalUser($request->user());

        $data = $this->validateVacationRequest($request);

        $vacationRequest = VacationRequest::create([
            'user_id' => $request->user()->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Solicitud de vacaciones enviada correctamente.',
            'vacation_request' => $this->formatVacationRequest($vacationRequest),
        ], 201);
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'vacation_requests' => VacationRequest::query()
                ->with(['user:id,name,email,role,is_approved', 'reviewer:id,name,email'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (VacationRequest $vacationRequest) => $this->formatVacationRequest($vacationRequest))
                ->values(),
        ]);
    }

    public function approve(Request $request, VacationRequest $vacationRequest): JsonResponse
    {
        return $this->review($request, $vacationRequest, 'approved', 'Solicitud de vacaciones aprobada.');
    }

    public function reject(Request $request, VacationRequest $vacationRequest): JsonResponse
    {
        return $this->review($request, $vacationRequest, 'rejected', 'Solicitud de vacaciones rechazada.');
    }

    private function review(Request $request, VacationRequest $vacationRequest, string $status, string $message): JsonResponse
    {
        if ($vacationRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Esta solicitud ya fue revisada.',
            ], 422);
        }

        $vacationRequest->fill([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => Carbon::now(config('app.timezone')),
        ]);

        $vacationRequest->save();

        return response()->json([
            'message' => $message,
            'vacation_request' => $this->formatVacationRequest(
                $vacationRequest->load(['user:id,name,email,role,is_approved', 'reviewer:id,name,email'])
            ),
        ]);
    }

    private function validateVacationRequest(Request $request): array
    {
        return $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
    }

    private function ensureProfessionalUser(?User $user): void
    {
        $role = $this->normalizeRole($user?->role);

        if (($role === 'profesional' || $role === 'cuidador_profesional') && (bool) $user?->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'Solo cuidadores profesionales aprobados pueden solicitar vacaciones.',
        ], 403));
    }

    private function formatVacationRequest(VacationRequest $vacationRequest): array
    {
        return [
            'id' => $vacationRequest->id,
            'user_id' => $vacationRequest->user_id,
            'user' => $vacationRequest->relationLoaded('user') && $vacationRequest->user ? [
                'id' => $vacationRequest->user->id,
                'name' => $vacationRequest->user->name,
                'email' => $vacationRequest->user->email,
                'role' => $vacationRequest->user->role,
                'is_approved' => $vacationRequest->user->is_approved,
            ] : null,
            'start_date' => $vacationRequest->start_date?->toDateString(),
            'end_date' => $vacationRequest->end_date?->toDateString(),
            'reason' => $vacationRequest->reason,
            'status' => $vacationRequest->status,
            'reviewed_at' => $vacationRequest->reviewed_at?->toDateTimeString(),
            'reviewer' => $vacationRequest->relationLoaded('reviewer') && $vacationRequest->reviewer ? [
                'id' => $vacationRequest->reviewer->id,
                'name' => $vacationRequest->reviewer->name,
                'email' => $vacationRequest->reviewer->email,
            ] : null,
        ];
    }

    private function normalizeRole(mixed $role): string
    {
        return Str::of((string) $role)->ascii()->lower()->trim()->toString();
    }
}

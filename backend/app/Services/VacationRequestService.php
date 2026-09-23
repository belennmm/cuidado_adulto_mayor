<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VacationRequestService
{
    public function forProfessional(User $user): Collection
    {
        $this->authorizeProfessional($user);

        return VacationRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, array $data): VacationRequest
    {
        $this->authorizeProfessional($user);

        return VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
    }

    public function allForAdmin(): Collection
    {
        return VacationRequest::query()
            ->with(['user:id,name,email,role,is_approved', 'reviewer:id,name,email'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();
    }

    public function review(
        VacationRequest $vacationRequest,
        User $reviewer,
        string $status,
    ): VacationRequest {
        if ($vacationRequest->status !== 'pending') {
            abort(response()->json(['message' => 'Esta solicitud ya fue revisada.'], 422));
        }

        $vacationRequest->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => Carbon::now(config('app.timezone')),
        ]);

        return $vacationRequest->load([
            'user:id,name,email,role,is_approved',
            'reviewer:id,name,email',
        ]);
    }

    private function authorizeProfessional(User $user): void
    {
        $role = Str::of((string) $user->role)->ascii()->lower()->trim()->toString();

        if (in_array($role, ['profesional', 'cuidador_profesional'], true) && $user->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'Solo cuidadores profesionales aprobados pueden solicitar vacaciones.',
        ], 403));
    }
}

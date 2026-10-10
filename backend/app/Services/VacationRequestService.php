<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\ListPage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class VacationRequestService
{
    public function forProfessional(User $user, ?array $input = null): Collection|LengthAwarePaginator
    {
        $this->authorizeProfessional($user);

        $query = VacationRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id');

        return $input === null ? $query->get() : ListPage::query($query, $input);
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

    public function allForAdmin(?array $input = null): Collection|LengthAwarePaginator
    {
        $query = VacationRequest::query()
            ->with(['user:id,name,email,role,is_approved', 'reviewer:id,name,email'])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['pending'])
            ->orderByDesc('created_at')->orderByDesc('id');

        return $input === null ? $query->get() : ListPage::query($query, $input);
    }

    public function review(
        VacationRequest $vacationRequest,
        User $reviewer,
        string $status,
    ): VacationRequest {
        Gate::forUser($reviewer)->authorize('review', $vacationRequest);
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
        if ($user->hasRole(UserRole::PROFESSIONAL) && $user->is_approved) {
            return;
        }

        abort(response()->json([
            'message' => 'Solo cuidadores profesionales aprobados pueden solicitar vacaciones.',
        ], 403));
    }
}

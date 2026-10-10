<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CaregiverSchedule;
use App\Models\User;
use App\Support\ListPage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class CaregiverScheduleService
{
    public function all(?array $input = null): Collection|LengthAwarePaginator
    {
        $query = CaregiverSchedule::query()
            ->with('user:id,name,email,role,is_approved')
            ->orderBy('day_of_week')
            ->orderBy('start_time')->orderBy('id');

        return $input === null ? $query->get() : ListPage::query($query, $input);
    }

    public function saveForCaregiver(User $user, array $data): CaregiverSchedule
    {
        $this->ensureCaregiverCanManage($user);

        return $this->save($user, $data);
    }

    public function saveForAdmin(array $data): CaregiverSchedule
    {
        $caregiver = User::query()->findOrFail($data['user_id']);
        $this->ensureCaregiverCanManage($caregiver);

        return $this->save($caregiver, $data)
            ->load('user:id,name,email,role,is_approved');
    }

    public function update(User $actor, CaregiverSchedule $schedule, array $data): CaregiverSchedule
    {
        Gate::forUser($actor)->authorize('update', $schedule);

        return $this->withLockedSchedule($schedule, function ($schedule) use ($data) {
            $this->ensureNoPendingChange($schedule);
            $schedule->update($this->scheduleAttributes($data));

            return $schedule;
        });
    }

    public function requestChange(User $actor, CaregiverSchedule $schedule, array $data): CaregiverSchedule
    {
        Gate::forUser($actor)->authorize('requestChange', $schedule);
        return $this->withLockedSchedule($schedule, function ($schedule) use ($data) {
            $this->ensureNoPendingChange($schedule);
            $schedule->update([
                'change_request_status' => 'pending',
                'change_request_start_time' => $data['start_time'],
                'change_request_end_time' => $data['end_time'],
                'change_request_notes' => $data['notes'] ?? null,
                'change_request_message' => $data['message'],
            ]);

            return $schedule;
        });
    }

    public function approveChange(CaregiverSchedule $schedule): CaregiverSchedule
    {
        return $this->withLockedSchedule($schedule, function ($schedule) {
            $this->ensurePendingChange($schedule);
            $schedule->fill([
                'start_time' => $schedule->change_request_start_time,
                'end_time' => $schedule->change_request_end_time,
                'notes' => $schedule->change_request_notes,
            ]);
            $this->clearChangeRequest($schedule);
            $schedule->save();

            return $this->loadUser($schedule);
        });
    }

    public function rejectChange(CaregiverSchedule $schedule): CaregiverSchedule
    {
        return $this->withLockedSchedule($schedule, function ($schedule) {
            $this->ensurePendingChange($schedule);
            $this->clearChangeRequest($schedule);
            $schedule->save();

            return $this->loadUser($schedule);
        });
    }

    private function save(User $caregiver, array $data): CaregiverSchedule
    {
        return DB::transaction(function () use ($caregiver, $data) {
            // Serialize upserts for a caregiver, including a day without an existing row.
            $caregiver = User::query()->lockForUpdate()->findOrFail($caregiver->getKey());
            $this->ensureCaregiverCanManage($caregiver);
            $existing = CaregiverSchedule::query()->where('user_id', $caregiver->id)
                ->where('day_of_week', $data['day_of_week'])->lockForUpdate()->first();
            if ($existing) {
                $this->ensureNoPendingChange($existing);
            }
            return CaregiverSchedule::updateOrCreate(
                ['user_id' => $caregiver->id, 'day_of_week' => $data['day_of_week']],
                $this->scheduleAttributes($data),
            );
        });
    }

    private function withLockedSchedule(CaregiverSchedule $schedule, callable $operation): CaregiverSchedule
    {
        return DB::transaction(fn () => $operation(
            CaregiverSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey()),
        ));
    }

    private function ensureNoPendingChange(CaregiverSchedule $schedule): void
    {
        if ($schedule->change_request_status === 'pending') {
            abort(response()->json(['message' => 'Revisa la solicitud pendiente antes de modificar este turno.'], 422));
        }
    }

    private function scheduleAttributes(array $data): array
    {
        return [
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function ensureCaregiverCanManage(User $user): void
    {
        if (! $user->hasRole(UserRole::PROFESSIONAL)) {
            abort(response()->json(['message' => 'No tienes acceso para definir horarios.'], 403));
        }

        if (! $user->is_approved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para definir horarios.',
            ], 403));
        }
    }

    private function ensurePendingChange(CaregiverSchedule $schedule): void
    {
        if ($schedule->change_request_status !== 'pending') {
            abort(response()->json([
                'message' => 'Este turno no tiene una solicitud pendiente.',
            ], 422));
        }
    }

    private function clearChangeRequest(CaregiverSchedule $schedule): void
    {
        $schedule->fill([
            'change_request_status' => null,
            'change_request_start_time' => null,
            'change_request_end_time' => null,
            'change_request_notes' => null,
            'change_request_message' => null,
        ]);
    }

    private function loadUser(CaregiverSchedule $schedule): CaregiverSchedule
    {
        return $schedule->load('user:id,name,email,role,is_approved');
    }
}

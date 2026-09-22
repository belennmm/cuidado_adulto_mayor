<?php

namespace App\Services;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CaregiverScheduleService
{
    public function all(): Collection
    {
        return CaregiverSchedule::query()
            ->with('user:id,name,email,role,is_approved')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
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
        $isAdmin = $this->normalizeRole($actor->role) === 'admin';

        if (! $isAdmin && (int) $schedule->user_id !== (int) $actor->id) {
            abort(response()->json([
                'message' => 'No tienes permiso para modificar este horario.',
            ], 403));
        }

        if (! $isAdmin) {
            $this->ensureCaregiverCanManage($actor);
        }

        $schedule->update($this->scheduleAttributes($data));

        return $schedule;
    }

    public function requestChange(User $actor, CaregiverSchedule $schedule, array $data): CaregiverSchedule
    {
        if ((int) $schedule->user_id !== (int) $actor->id) {
            abort(response()->json([
                'message' => 'No tienes permiso para solicitar cambios en este horario.',
            ], 403));
        }

        $this->ensureCaregiverCanManage($actor);
        $schedule->update([
            'change_request_status' => 'pending',
            'change_request_start_time' => $data['start_time'],
            'change_request_end_time' => $data['end_time'],
            'change_request_notes' => $data['notes'] ?? null,
            'change_request_message' => $data['message'],
        ]);

        return $schedule;
    }

    public function approveChange(CaregiverSchedule $schedule): CaregiverSchedule
    {
        $this->ensurePendingChange($schedule);
        $schedule->fill([
            'start_time' => $schedule->change_request_start_time,
            'end_time' => $schedule->change_request_end_time,
            'notes' => $schedule->change_request_notes,
        ]);
        $this->clearChangeRequest($schedule);
        $schedule->save();

        return $this->loadUser($schedule);
    }

    public function rejectChange(CaregiverSchedule $schedule): CaregiverSchedule
    {
        $this->ensurePendingChange($schedule);
        $this->clearChangeRequest($schedule);
        $schedule->save();

        return $this->loadUser($schedule);
    }

    private function save(User $caregiver, array $data): CaregiverSchedule
    {
        return CaregiverSchedule::updateOrCreate(
            ['user_id' => $caregiver->id, 'day_of_week' => $data['day_of_week']],
            $this->scheduleAttributes($data),
        );
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
        if (! in_array($this->normalizeRole($user->role), ['profesional', 'cuidador_profesional'], true)) {
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

    private function normalizeRole(mixed $role): string
    {
        return Str::of((string) $role)->ascii()->lower()->trim()->toString();
    }
}

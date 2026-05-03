<?php

namespace App\Http\Controllers;

use App\Models\CaregiverSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CaregiverScheduleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCaregiverCanManageSchedule($user?->role, (bool) $user?->is_approved);

        $data = $this->validateSchedulePayload($request);

        $schedule = CaregiverSchedule::updateOrCreate(
            [
                'user_id' => $user->id,
                'day_of_week' => $data['day_of_week'],
            ],
            [
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'notes' => $data['notes'] ?? null,
            ],
        );

        return response()->json([
            'message' => 'Horario guardado correctamente.',
            'schedule' => $this->formatSchedule($schedule),
        ], 201);
    }

    public function update(Request $request, CaregiverSchedule $schedule): JsonResponse
    {
        $user = $request->user();
        $role = $this->normalizeRole($user?->role);

        if ($role !== 'admin' && (int) $schedule->user_id !== (int) $user?->id) {
            return response()->json([
                'message' => 'No tienes permiso para modificar este horario.',
            ], 403);
        }

        if ($role !== 'admin') {
            $this->ensureCaregiverCanManageSchedule($user?->role, (bool) $user?->is_approved);
        }

        $data = $this->validateSchedulePayload($request);

        $schedule->fill([
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'notes' => $data['notes'] ?? null,
        ]);

        $schedule->save();

        return response()->json([
            'message' => 'Horario actualizado correctamente.',
            'schedule' => $this->formatSchedule($schedule),
        ]);
    }

    private function validateSchedulePayload(Request $request): array
    {
        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $timezone = (string) config('app.timezone');

        $start = Carbon::createFromFormat('H:i', $data['start_time'], $timezone);
        $end = Carbon::createFromFormat('H:i', $data['end_time'], $timezone);

        if ($end->lessThanOrEqualTo($start)) {
            abort(response()->json([
                'message' => 'El horario es inválido.',
                'errors' => [
                    'end_time' => ['end_time debe ser mayor que start_time.'],
                ],
            ], 422));
        }

        return $data;
    }

    private function ensureCaregiverCanManageSchedule(mixed $role, bool $isApproved): void
    {
        $normalized = $this->normalizeRole($role);

        if (!in_array($normalized, ['profesional', 'cuidador_profesional'], true)) {
            abort(response()->json([
                'message' => 'No tienes acceso para definir horarios.',
            ], 403));
        }

        if (!$isApproved) {
            abort(response()->json([
                'message' => 'Tu cuenta debe estar aprobada para definir horarios.',
            ], 403));
        }
    }

    private function formatSchedule(CaregiverSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'user_id' => $schedule->user_id,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'notes' => $schedule->notes,
        ];
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


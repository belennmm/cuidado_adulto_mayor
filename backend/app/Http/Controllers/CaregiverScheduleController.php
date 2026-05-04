<?php

namespace App\Http\Controllers;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CaregiverScheduleController extends Controller
{
    public function adminIndex(): JsonResponse
    {
        $schedules = CaregiverSchedule::query()
            ->with('user:id,name,email,role,is_approved')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (CaregiverSchedule $schedule) => $this->formatSchedule($schedule));

        return response()->json(['schedules' => $schedules]);
    }

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

    public function adminStore(Request $request): JsonResponse
    {
        $data = $this->validateSchedulePayload($request, true);

        $caregiver = User::query()->findOrFail($data['user_id']);
        $this->ensureCaregiverCanManageSchedule($caregiver->role, (bool) $caregiver->is_approved);

        $schedule = CaregiverSchedule::updateOrCreate(
            [
                'user_id' => $caregiver->id,
                'day_of_week' => $data['day_of_week'],
            ],
            [
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'notes' => $data['notes'] ?? null,
            ],
        );

        return response()->json([
            'message' => 'Turno asignado correctamente.',
            'schedule' => $this->formatSchedule($schedule->load('user:id,name,email,role,is_approved')),
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

    public function destroy(CaregiverSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Turno eliminado correctamente.',
        ]);
    }

    private function validateSchedulePayload(Request $request, bool $requiresUserId = false): array
    {
        $rules = [
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if ($requiresUserId) {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', 'profesional')->where('is_approved', true),
            ];
        }

        $data = $request->validate($rules);

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
        $user = $schedule->relationLoaded('user') && $schedule->user
            ? [
                'id' => $schedule->user->id,
                'name' => $schedule->user->name,
                'email' => $schedule->user->email,
                'role' => $schedule->user->role,
                'is_approved' => $schedule->user->is_approved,
            ]
            : null;

        return [
            'id' => $schedule->id,
            'user_id' => $schedule->user_id,
            'user' => $user,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => $this->formatTime($schedule->start_time),
            'end_time' => $this->formatTime($schedule->end_time),
            'notes' => $schedule->notes,
        ];
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return Carbon::createFromFormat(
            strlen((string) $time) === 5 ? 'H:i' : 'H:i:s',
            (string) $time,
            (string) config('app.timezone'),
        )->format('H:i:s');
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

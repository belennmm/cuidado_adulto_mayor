<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CaregiverScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') && $this->user ? [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->user->role,
            'is_approved' => $this->user->is_approved,
        ] : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $user,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'notes' => $this->notes,
            'change_request' => $this->change_request_status ? [
                'status' => $this->change_request_status,
                'start_time' => $this->formatTime($this->change_request_start_time),
                'end_time' => $this->formatTime($this->change_request_end_time),
                'notes' => $this->change_request_notes,
                'message' => $this->change_request_message,
            ] : null,
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
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaregiverScheduleInvalidFormatTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidStartTimes')]
    public function test_invalid_schedule_time_format_is_rejected(string $invalidStartTime): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $payload = [
            'day_of_week' => 2,
            'start_time' => $invalidStartTime,
            'end_time' => '16:00',
            'notes' => 'Horario con formato invalido',
        ];

        $this->assertDatabaseCount('caregiver_schedules', 0);

        $this->postJson('/api/schedules', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_time');

        $this->assertDatabaseCount('caregiver_schedules', 0);
    }

    public static function invalidStartTimes(): array
    {
        return [
            'hora fuera de rango' => ['25:80'],
            'texto no valido' => ['hora-invalida'],
        ];
    }
}

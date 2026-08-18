<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaregiverScheduleCalendarScrumTest extends TestCase
{
    use RefreshDatabase;

    // SCRUM-615: crear varios horarios.
    #[Test]
    public function scrum_615_admin_creates_several_schedules(): void
    {
        $admin = $this->createAdmin();
        $caregivers = $this->createCaregivers();

        Sanctum::actingAs($admin);

        $schedules = [
            [$caregivers[0], 1, '08:00', '16:00', 'Turno de prueba - lunes'],
            [$caregivers[1], 3, '07:00', '15:00', 'Turno de prueba - miercoles'],
            [$caregivers[2], 5, '10:00', '18:00', 'Turno de prueba - viernes'],
        ];

        foreach ($schedules as [$caregiver, $day, $start, $end, $notes]) {
            $this->postJson('/api/admin/schedules', [
                'user_id' => $caregiver->id,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'notes' => $notes,
            ])->assertCreated();
        }

        $this->assertDatabaseCount('caregiver_schedules', 3);

        foreach ($schedules as [$caregiver, $day, $start, $end, $notes]) {
            $this->assertDatabaseHas('caregiver_schedules', [
                'user_id' => $caregiver->id,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'notes' => $notes,
            ]);
        }
    }

    // SCRUM-616: autenticar un administrador.
    #[Test]
    public function scrum_616_authenticates_an_administrator(): void
    {
        $admin = $this->createAdmin('admin@test.com', 'chon1234');

        $response = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'chon1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    // SCRUM-617: consultar el calendario.
    #[Test]
    public function scrum_617_admin_queries_the_schedule_calendar(): void
    {
        $admin = $this->createAdmin();
        $caregivers = $this->createCaregivers();
        $this->seedSchedules($caregivers);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/schedules/calendar?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()
            ->assertJsonPath('range.start_date', '2026-08-01')
            ->assertJsonPath('range.end_date', '2026-08-31')
            ->assertJsonCount(13, 'shifts')
            ->assertJsonCount(13, 'events');
    }

    // SCRUM-618: verificar estado 200 y estructura JSON.
    #[Test]
    public function scrum_618_calendar_returns_200_with_expected_json_structure(): void
    {
        $admin = $this->createAdmin();
        $caregivers = $this->createCaregivers();
        $this->seedSchedules($caregivers);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/schedules/calendar?start_date=2026-08-01&end_date=2026-08-31')
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'range' => ['start_date', 'end_date'],
                'shifts' => [
                    '*' => [
                        'id',
                        'schedule_id',
                        'caregiver_id',
                        'caregiver_name',
                        'caregiver_email',
                        'older_adult_id',
                        'older_adult_name',
                        'older_adult_room',
                        'date',
                        'day_of_week',
                        'start_time',
                        'end_time',
                        'status',
                        'notes',
                    ],
                ],
                'events' => [
                    '*' => ['id', 'type', 'title', 'date', 'time', 'person', 'status', 'description'],
                ],
            ]);
    }

    // SCRUM-619: comprobar fechas y cuidadores retornados.
    #[Test]
    public function scrum_619_calendar_returns_correct_dates_and_caregivers(): void
    {
        $admin = $this->createAdmin();
        $caregivers = $this->createCaregivers();
        $this->seedSchedules($caregivers);

        Sanctum::actingAs($admin);

        $shifts = $this
            ->getJson('/api/admin/schedules/calendar?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()
            ->json('shifts');

        $expected = [
            $caregivers[0]->id => ['name' => 'Belen', 'day' => 1, 'start' => '08:00:00', 'end' => '16:00:00', 'count' => 5],
            $caregivers[1]->id => ['name' => 'Makoto', 'day' => 3, 'start' => '07:00:00', 'end' => '15:00:00', 'count' => 4],
            $caregivers[2]->id => ['name' => 'Pablo', 'day' => 5, 'start' => '10:00:00', 'end' => '18:00:00', 'count' => 4],
        ];

        $this->assertCount(13, $shifts);

        foreach ($shifts as $shift) {
            $date = new \DateTimeImmutable($shift['date']);
            $caregiver = $expected[$shift['caregiver_id']] ?? null;

            $this->assertNotNull($caregiver, 'El calendario retorno un cuidador inesperado.');
            $this->assertGreaterThanOrEqual('2026-08-01', $shift['date']);
            $this->assertLessThanOrEqual('2026-08-31', $shift['date']);
            $this->assertSame($caregiver['name'], $shift['caregiver_name']);
            $this->assertSame($caregiver['day'], $shift['day_of_week']);
            $this->assertSame($caregiver['day'], (int) $date->format('w'));
            $this->assertSame($caregiver['start'], $shift['start_time']);
            $this->assertSame($caregiver['end'], $shift['end_time']);
        }

        foreach ($expected as $caregiverId => $caregiver) {
            $actualCount = count(array_filter(
                $shifts,
                fn (array $shift): bool => $shift['caregiver_id'] === $caregiverId
            ));

            $this->assertSame($caregiver['count'], $actualCount);
        }

        $sortKeys = array_map(
            fn (array $shift): string => implode('|', [
                $shift['date'],
                $shift['start_time'],
                $shift['caregiver_name'],
                $shift['older_adult_name'],
            ]),
            $shifts
        );
        $sortedKeys = $sortKeys;
        sort($sortedKeys);

        $this->assertSame($sortedKeys, $sortKeys, 'Los turnos no estan ordenados cronologicamente.');
    }

    private function createAdmin(string $email = 'calendar.admin@test.com', string $password = 'password123'): User
    {
        return User::factory()->create([
            'name' => 'Administrador de calendario',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_approved' => true,
        ]);
    }

    /** @return array<int, User> */
    private function createCaregivers(): array
    {
        return [
            User::factory()->create(['name' => 'Belen', 'email' => 'belen@test.com', 'role' => 'profesional', 'is_approved' => true]),
            User::factory()->create(['name' => 'Makoto', 'email' => 'makoto@test.com', 'role' => 'profesional', 'is_approved' => true]),
            User::factory()->create(['name' => 'Pablo', 'email' => 'pablo@test.com', 'role' => 'profesional', 'is_approved' => true]),
        ];
    }

    /** @param array<int, User> $caregivers */
    private function seedSchedules(array $caregivers): void
    {
        $schedules = [
            [$caregivers[0], 1, '08:00:00', '16:00:00', 'Turno de prueba - lunes'],
            [$caregivers[1], 3, '07:00:00', '15:00:00', 'Turno de prueba - miercoles'],
            [$caregivers[2], 5, '10:00:00', '18:00:00', 'Turno de prueba - viernes'],
        ];

        foreach ($schedules as [$caregiver, $day, $start, $end, $notes]) {
            CaregiverSchedule::create([
                'user_id' => $caregiver->id,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'notes' => $notes,
            ]);
        }
    }
}

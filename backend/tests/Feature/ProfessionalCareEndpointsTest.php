<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalCareEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_overview_returns_only_assigned_care_information(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 09:00:00'));

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $otherProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        OlderAdult::create([
            'full_name' => 'Miguel Herrera',
            'age' => 76,
            'status' => 'Atencion',
            'professional_caregiver_id' => $otherProfessional->id,
            'created_by' => $otherProfessional->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        OlderAdultMedication::create([
            'older_adult_id' => $assignedAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '8:00 AM',
            'days' => ['lunes'],
            'is_active' => true,
        ]);

        CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 1,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/professional/overview')
            ->assertOk()
            ->assertJsonPath('stats.older_adults', 1)
            ->assertJsonPath('stats.medications_today', 1)
            ->assertJsonPath('stats.schedules', 1)
            ->assertJsonPath('older_adults.0.full_name', 'Rosa Martinez')
            ->assertJsonPath('next_medications.0.medication_name', 'Losartan')
            ->assertJsonMissing(['full_name' => 'Miguel Herrera']);
    }

    public function test_non_professional_user_cannot_open_professional_endpoints(): void
    {
        $family = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($family);

        $this->getJson('/api/professional/overview')->assertForbidden();
        $this->getJson('/api/professional/older-adults')->assertForbidden();
        $this->getJson('/api/professional/routines')->assertForbidden();
        $this->getJson('/api/professional/schedules')->assertForbidden();
    }

    public function test_professional_schedules_returns_own_schedules(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $otherProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        CaregiverSchedule::create([
            'user_id' => $otherProfessional->id,
            'day_of_week' => 3,
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/professional/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'schedules')
            ->assertJsonPath('schedules.0.day_of_week', 2)
            ->assertJsonPath('schedules.0.start_time', '09:00:00');
    }
}

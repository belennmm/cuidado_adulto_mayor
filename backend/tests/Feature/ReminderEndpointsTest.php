<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_sees_only_assigned_reminders_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 09:00:00')); // Monday

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $otherProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $otherAdult = OlderAdult::create([
            'full_name' => 'Miguel Herrera',
            'status' => 'Estable',
            'professional_caregiver_id' => $otherProfessional->id,
            'created_by' => $otherProfessional->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        $dueAssignment = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00 AM',
            'days' => ['lunes'],
            'is_active' => true,
        ]);

        OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '09:00 AM',
            'days' => ['martes'],
            'is_active' => true,
        ]);

        OlderAdultMedication::create([
            'older_adult_id' => $otherAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00 AM',
            'days' => ['lunes'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/professional/reminders')
            ->assertOk()
            ->assertJsonPath('date', Carbon::today()->toDateString())
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('reminders.0.older_adult_medication_id', $dueAssignment->id)
            ->assertJsonPath('reminders.0.administered_today', false);
    }

    public function test_reminders_marks_administered_today_when_administration_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 09:00:00')); // Monday

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00 AM',
            'days' => ['lunes'],
            'is_active' => true,
        ]);

        MedicationAdministration::create([
            'older_adult_id' => $olderAdult->id,
            'older_adult_medication_id' => $assignment->id,
            'medication_id' => $medication->id,
            'administration_type' => 'scheduled',
            'dosage' => '1 tableta',
            'administration_date' => Carbon::today()->toDateString(),
            'administration_time' => '08:15:00',
            'recorded_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/professional/reminders')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.administered', 1)
            ->assertJsonPath('reminders.0.administered_today', true);
    }

    public function test_non_professional_user_cannot_open_reminders(): void
    {
        $family = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($family);

        $this->getJson('/api/professional/reminders')->assertForbidden();
    }
}


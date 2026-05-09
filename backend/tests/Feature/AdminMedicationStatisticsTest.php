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

class AdminMedicationStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_real_medication_statistics(): void
    {
        Carbon::setTestNow('2026-05-09 10:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $admin->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00',
            'days' => ['sabado'],
            'is_active' => true,
        ]);

        MedicationAdministration::create([
            'older_adult_id' => $olderAdult->id,
            'older_adult_medication_id' => $assignment->id,
            'medication_id' => $medication->id,
            'administration_type' => 'scheduled',
            'dosage' => '1 tableta',
            'administration_date' => '2026-05-09',
            'administration_time' => '08:15:00',
            'recorded_by' => $professional->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/medication-statistics?filter=day')
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Losartan')
            ->assertJsonPath('items.0.totalUses', 1)
            ->assertJsonPath('items.0.patients', 1)
            ->assertJsonPath('inventory.0.name', 'Losartan')
            ->assertJsonPath('inventory.0.assigned_patients', 1);

        Carbon::setTestNow();
    }
}

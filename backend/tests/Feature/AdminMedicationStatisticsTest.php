<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAcquisition;
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

    public function test_admin_statistics_sum_acquired_units_for_day_month_and_year(): void
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
            'presentation' => 'Tableta 50mg',
            'quantity' => 18,
            'unit' => 'tabletas',
            'minimum_stock' => 5,
            'expiration_date' => '2030-12-31',
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

        MedicationAcquisition::create([
            'medication_id' => $medication->id,
            'older_adult_id' => $olderAdult->id,
            'quantity' => 10,
            'acquired_at' => '2026-05-09 08:10:00',
        ]);
        MedicationAcquisition::create([
            'medication_id' => $medication->id,
            'older_adult_id' => null,
            'quantity' => 20,
            'acquired_at' => '2026-05-09 10:00:00',
        ]);
        $otherMedication = Medication::create(['name' => 'Paracetamol', 'is_active' => true]);
        MedicationAcquisition::create([
            'medication_id' => $otherMedication->id,
            'older_adult_id' => null,
            'quantity' => 40,
            'acquired_at' => '2026-05-09 12:00:00',
        ]);

        Sanctum::actingAs($admin);

        $dayResponse = $this->getJson('/api/admin/medication-statistics?filter=day')
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Paracetamol')
            ->assertJsonPath('items.0.unitsAcquired', 40)
            ->assertJsonPath('items.1.name', 'Losartan')
            ->assertJsonPath('items.1.unitsAcquired', 30)
            ->assertJsonPath('items.1.acquisitionsCount', 2)
            ->assertJsonPath('items.1.acquisitionLabel', '30 unidades adquiridas hoy')
            ->assertJsonPath('inventory.0.name', 'Losartan')
            ->assertJsonPath('inventory.0.older_adult_id', $olderAdult->id)
            ->assertJsonPath('inventory.0.older_adult_name', 'Rosa Martinez')
            ->assertJsonPath('inventory.0.quantity', 18)
            ->assertJsonPath('inventory.0.assigned_patients', 1);

        $this->assertSame(30, array_sum(array_column($dayResponse->json('items.1.chart'), 'value')));

        $monthResponse = $this->getJson('/api/admin/medication-statistics?filter=month')
            ->assertOk()
            ->assertJsonPath('items.1.unitsAcquired', 30)
            ->assertJsonPath('items.1.acquisitionLabel', '30 unidades adquiridas este mes');
        $this->assertSame(30, array_sum(array_column($monthResponse->json('items.1.chart'), 'value')));

        $yearResponse = $this->getJson('/api/admin/medication-statistics?filter=year')
            ->assertOk()
            ->assertJsonPath('items.1.unitsAcquired', 30)
            ->assertJsonPath('items.1.acquisitionLabel', '30 unidades adquiridas este año');
        $this->assertSame(30, array_sum(array_column($yearResponse->json('items.1.chart'), 'value')));

        Carbon::setTestNow();
    }
}

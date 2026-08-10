<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_show_an_older_adult_by_id(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $familyCaregiver = User::factory()->create([
            'name' => 'Cuidadora Familiar',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $professionalCaregiver = User::factory()->create([
            'name' => 'Cuidadora Profesional',
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 82,
            'birthdate' => '1944-05-12',
            'gender' => 'Femenino',
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => $familyCaregiver->name,
            'family_caregiver_id' => $familyCaregiver->id,
            'professional_caregiver_id' => $professionalCaregiver->id,
            'emergency_contact_name' => 'Ana Martinez',
            'emergency_contact_phone' => '5555-1111',
            'allergies' => 'Penicilina',
            'medical_history' => 'Hipertension',
            'notes' => 'Revisar presion arterial',
            'created_by' => $admin->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'presentation' => 'Tableta 50mg',
            'quantity' => 24,
            'unit' => 'tabletas',
            'minimum_stock' => 5,
            'expiration_date' => '2030-12-31',
            'is_active' => true,
        ]);

        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00',
            'days' => ['lunes', 'miercoles'],
            'notes' => 'Despues del desayuno',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/older-adults/{$olderAdult->id}")
            ->assertOk()
            ->assertJsonStructure([
                'older_adult' => [
                    'id',
                    'full_name',
                    'age',
                    'birthdate',
                    'gender',
                    'room',
                    'status',
                    'caregiver_family',
                    'family_caregiver_id',
                    'family_caregiver_name',
                    'professional_caregiver_id',
                    'professional_caregiver_name',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'allergies',
                    'medical_history',
                    'notes',
                    'medications',
                    'created_at',
                ],
            ])
            ->assertJsonPath('older_adult.id', $olderAdult->id)
            ->assertJsonPath('older_adult.full_name', 'Rosa Martinez')
            ->assertJsonPath('older_adult.age', 82)
            ->assertJsonPath('older_adult.birthdate', '1944-05-12')
            ->assertJsonPath('older_adult.room', 'A-101')
            ->assertJsonPath('older_adult.family_caregiver_name', 'Cuidadora Familiar')
            ->assertJsonPath('older_adult.professional_caregiver_name', 'Cuidadora Profesional')
            ->assertJsonPath('older_adult.medications.0.id', $assignment->id)
            ->assertJsonPath('older_adult.medications.0.medication_id', $medication->id)
            ->assertJsonPath('older_adult.medications.0.name', 'Losartan')
            ->assertJsonPath('older_adult.medications.0.dosage', '1 tableta')
            ->assertJsonPath('older_adult.medications.0.schedule', '08:00')
            ->assertJsonPath('older_adult.medications.0.days', ['lunes', 'miercoles'])
            ->assertJsonPath('older_adult.medications.0.notes', 'Despues del desayuno')
            ->assertJsonPath('older_adult.medications.0.is_active', true);
    }
}

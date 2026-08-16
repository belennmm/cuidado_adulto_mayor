<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_older_adult(): void
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

        Sanctum::actingAs($admin);

        $payload = [
            'full_name' => 'Rosa Martinez',
            'age' => 82,
            'birthdate' => '1944-05-12',
            'gender' => 'Femenino',
            'room' => 'A-101',
            'status' => 'Estable',
            'family_caregiver_id' => $familyCaregiver->id,
            'professional_caregiver_id' => $professionalCaregiver->id,
            'emergency_contact_name' => 'Ana Martinez',
            'emergency_contact_phone' => '5555-1111',
            'allergies' => 'Penicilina',
            'medical_history' => 'Hipertension',
            'notes' => 'Revisar presion arterial',
            'medications' => [
                [
                    'name' => 'Losartan',
                    'dosage' => '1 tableta',
                    'schedule' => '08:00',
                    'days' => ['lunes', 'miercoles'],
                    'notes' => 'Despues del desayuno',
                ],
            ],
        ];

        $this->postJson('/api/admin/older-adults', $payload)
            ->assertCreated()
            ->assertJsonStructure([
                'message',
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
            ->assertJsonPath('message', 'Adulto mayor creado correctamente.')
            ->assertJsonPath('older_adult.full_name', 'Rosa Martinez')
            ->assertJsonPath('older_adult.age', 82)
            ->assertJsonPath('older_adult.birthdate', '1944-05-12')
            ->assertJsonPath('older_adult.room', 'A-101')
            ->assertJsonPath('older_adult.family_caregiver_id', $familyCaregiver->id)
            ->assertJsonPath('older_adult.family_caregiver_name', 'Cuidadora Familiar')
            ->assertJsonPath('older_adult.professional_caregiver_id', $professionalCaregiver->id)
            ->assertJsonPath('older_adult.professional_caregiver_name', 'Cuidadora Profesional')
            ->assertJsonPath('older_adult.medications.0.name', 'Losartan')
            ->assertJsonPath('older_adult.medications.0.dosage', '1 tableta')
            ->assertJsonPath('older_adult.medications.0.schedule', '08:00')
            ->assertJsonPath('older_adult.medications.0.days', ['lunes', 'miercoles'])
            ->assertJsonPath('older_adult.medications.0.notes', 'Despues del desayuno')
            ->assertJsonPath('older_adult.medications.0.is_active', true);

        $this->assertDatabaseHas('older_adults', [
            'full_name' => 'Rosa Martinez',
            'age' => 82,
            'birthdate' => '1944-05-12 00:00:00',
            'room' => 'A-101',
            'created_by' => $admin->id,
            'family_caregiver_id' => $familyCaregiver->id,
            'professional_caregiver_id' => $professionalCaregiver->id,
        ]);

        $this->assertDatabaseHas('medications', [
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('older_adult_medications', [
            'dosage' => '1 tableta',
            'schedule' => '08:00',
            'notes' => 'Despues del desayuno',
            'is_active' => true,
        ]);
    }
}

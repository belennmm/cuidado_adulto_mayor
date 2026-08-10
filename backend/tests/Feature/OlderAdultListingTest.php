<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_older_adults(): void
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

        $rosa = OlderAdult::create([
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

        $alberto = OlderAdult::create([
            'full_name' => 'Alberto Gomez',
            'age' => 78,
            'gender' => 'Masculino',
            'room' => 'B-202',
            'status' => 'En observacion',
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/older-adults')
            ->assertOk()
            ->assertJsonCount(2, 'older_adults')
            ->assertJsonStructure([
                'older_adults' => [
                    '*' => [
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
                ],
            ])
            ->assertJsonPath('older_adults.0.id', $alberto->id)
            ->assertJsonPath('older_adults.0.full_name', 'Alberto Gomez')
            ->assertJsonPath('older_adults.0.age', 78)
            ->assertJsonPath('older_adults.0.room', 'B-202')
            ->assertJsonPath('older_adults.0.status', 'En observacion')
            ->assertJsonPath('older_adults.0.medications', [])
            ->assertJsonPath('older_adults.1.id', $rosa->id)
            ->assertJsonPath('older_adults.1.full_name', 'Rosa Martinez')
            ->assertJsonPath('older_adults.1.age', 82)
            ->assertJsonPath('older_adults.1.birthdate', '1944-05-12')
            ->assertJsonPath('older_adults.1.room', 'A-101')
            ->assertJsonPath('older_adults.1.family_caregiver_name', 'Cuidadora Familiar')
            ->assertJsonPath('older_adults.1.professional_caregiver_name', 'Cuidadora Profesional')
            ->assertJsonPath('older_adults.1.medications', []);
    }
}

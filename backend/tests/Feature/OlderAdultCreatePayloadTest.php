<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultCreatePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_prepare_valid_older_adult_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $familyCaregiver = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $professionalCaregiver = User::factory()->create([
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

        $this->assertAuthenticatedAs($admin, 'sanctum');
        $this->assertSame('Rosa Martinez', $payload['full_name']);
        $this->assertSame($familyCaregiver->id, $payload['family_caregiver_id']);
        $this->assertSame($professionalCaregiver->id, $payload['professional_caregiver_id']);
        $this->assertSame('Losartan', $payload['medications'][0]['name']);
        $this->assertDatabaseCount('older_adults', 0);
        $this->assertDatabaseCount('older_adult_medications', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FamilyCareEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_family_overview_returns_only_assigned_care_information(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 09:00:00'));

        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'email' => 'laura.rodriguez@example.com',
            'role' => 'familiar',
            'is_approved' => true,
            'phone' => '5555-0104',
            'location' => 'Villa Nueva',
        ]);

        $professional = User::factory()->create([
            'name' => 'Maria Gonzalez',
            'email' => 'maria.gonzalez@example.com',
            'role' => 'profesional',
            'is_approved' => true,
            'phone' => '5555-0102',
            'location' => 'Zona 10',
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => 'Laura Rodriguez',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        OlderAdult::create([
            'full_name' => 'Miguel Herrera',
            'age' => 76,
            'room' => 'B-204',
            'status' => 'Atencion',
            'caregiver_family' => 'Otra Familia',
            'created_by' => $professional->id,
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
            'days' => ['jueves'],
            'is_active' => true,
        ]);

        Incident::create([
            'title' => 'Revision de presion',
            'description' => 'Se notifico lectura elevada.',
            'adult_name' => 'Rosa Martinez',
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        Incident::create([
            'title' => 'Incidente no familiar',
            'adult_name' => 'Miguel Herrera',
            'severity' => 'alta',
            'status' => 'abierto',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '09:30:00',
            'reported_by' => $professional->id,
        ]);

        Sanctum::actingAs($family);

        $response = $this->getJson('/api/family/overview');

        $response
            ->assertOk()
            ->assertJsonPath('stats.older_adults', 1)
            ->assertJsonPath('stats.medications_today', 1)
            ->assertJsonPath('stats.incidents_today', 1)
            ->assertJsonPath('older_adults.0.full_name', 'Rosa Martinez')
            ->assertJsonPath('next_medications.0.medication_name', 'Losartan')
            ->assertJsonMissing(['adult_name' => 'Miguel Herrera']);
    }

    public function test_non_family_user_cannot_open_family_endpoints(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/family/overview')
            ->assertForbidden();

        $this->getJson("/api/family/older-adults/{$olderAdult->id}")
            ->assertForbidden();

        $this->getJson('/api/family/routines')
            ->assertForbidden();

        $this->getJson('/api/family/incidents')
            ->assertForbidden();
    }

    public function test_pending_family_user_cannot_open_family_endpoints(): void
    {
        $family = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($family);

        $this->getJson('/api/family/routines')
            ->assertForbidden();

        $this->getJson('/api/family/incidents')
            ->assertForbidden();

        $this->getJson('/api/incidents/today')
            ->assertForbidden();
    }

    public function test_family_routines_endpoint_returns_only_assigned_adult_routines(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 09:00:00'));

        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'email' => 'laura.rodriguez@example.com',
            'role' => 'familiar',
            'is_approved' => true,
            'phone' => '5555-0104',
            'location' => 'Villa Nueva',
        ]);

        $professional = User::factory()->create([
            'name' => 'Maria Gonzalez',
            'email' => 'maria.gonzalez@example.com',
            'role' => 'profesional',
            'is_approved' => true,
            'phone' => '5555-0102',
            'location' => 'Zona 10',
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => 'Laura Rodriguez',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $otherAdult = OlderAdult::create([
            'full_name' => 'Miguel Herrera',
            'age' => 76,
            'room' => 'B-204',
            'status' => 'Atencion',
            'caregiver_family' => 'Otra Familia',
            'created_by' => $professional->id,
        ]);

        $assignedMedication = Medication::create([
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        $otherMedication = Medication::create([
            'name' => 'Metformina',
            'is_active' => true,
        ]);

        OlderAdultMedication::create([
            'older_adult_id' => $assignedAdult->id,
            'medication_id' => $assignedMedication->id,
            'dosage' => '1 tableta',
            'schedule' => '8:00 AM',
            'days' => ['jueves'],
            'is_active' => true,
        ]);

        OlderAdultMedication::create([
            'older_adult_id' => $otherAdult->id,
            'medication_id' => $otherMedication->id,
            'dosage' => '1 tableta',
            'schedule' => '9:00 AM',
            'days' => ['jueves'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($family);

        $this->getJson('/api/family/routines')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.today', 1)
            ->assertJsonPath('routine.0.older_adult_name', 'Rosa Martinez')
            ->assertJsonPath('routine.0.medication_name', 'Losartan')
            ->assertJsonMissing(['older_adult_name' => 'Miguel Herrera'])
            ->assertJsonMissing(['medication_name' => 'Metformina']);
    }

    public function test_family_routines_endpoint_validates_requested_adult_belongs_to_family(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 09:00:00'));

        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $otherFamily = User::factory()->create([
            'name' => 'Ana Lopez',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'caregiver_family' => 'Laura Rodriguez',
            'family_caregiver_id' => $family->id,
            'created_by' => $family->id,
        ]);

        $otherAdult = OlderAdult::create([
            'full_name' => 'Elena Castillo',
            'status' => 'Critico',
            'caregiver_family' => 'Ana Lopez',
            'family_caregiver_id' => $otherFamily->id,
            'created_by' => $otherFamily->id,
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
            'days' => ['jueves'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($family);

        $this->getJson("/api/family/routines?older_adult_id={$assignedAdult->id}")
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('routine.0.older_adult_id', $assignedAdult->id);

        $this->getJson("/api/family/routines?older_adult_id={$otherAdult->id}")
            ->assertForbidden();
    }

    public function test_family_can_get_complete_assigned_older_adult_info_with_incidents(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00'));

        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'email' => 'laura.rodriguez@example.com',
            'role' => 'familiar',
            'is_approved' => true,
            'phone' => '5555-0104',
            'location' => 'Villa Nueva',
        ]);

        $otherFamily = User::factory()->create([
            'name' => 'Ana Lopez',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'name' => 'Maria Gonzalez',
            'email' => 'maria.gonzalez@example.com',
            'role' => 'profesional',
            'is_approved' => true,
            'phone' => '5555-0102',
            'location' => 'Zona 10',
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'birthdate' => '1944-02-12',
            'gender' => 'Femenino',
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => 'Laura Rodriguez',
            'family_caregiver_id' => $family->id,
            'professional_caregiver_id' => $professional->id,
            'emergency_contact_name' => 'Carolina Martinez',
            'emergency_contact_phone' => '5555-2101',
            'allergies' => 'Penicilina',
            'medical_history' => 'Hipertension controlada.',
            'notes' => 'Requiere apoyo para desplazamientos largos.',
            'created_by' => $professional->id,
        ]);

        $otherAdult = OlderAdult::create([
            'full_name' => 'Elena Castillo',
            'status' => 'Critico',
            'caregiver_family' => 'Ana Lopez',
            'family_caregiver_id' => $otherFamily->id,
            'created_by' => $professional->id,
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
            'days' => ['viernes'],
            'notes' => 'Despues del desayuno.',
            'start_date' => '2026-04-01',
            'is_active' => true,
        ]);

        Incident::create([
            'title' => 'Revision de presion',
            'description' => 'Se notifico lectura elevada.',
            'adult_name' => 'Rosa Martinez',
            'older_adult_id' => $assignedAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        Sanctum::actingAs($family);

        $this->getJson("/api/family/older-adults/{$assignedAdult->id}")
            ->assertOk()
            ->assertJsonPath('older_adult.id', $assignedAdult->id)
            ->assertJsonPath('older_adult.full_name', 'Rosa Martinez')
            ->assertJsonPath('older_adult.family_caregiver.id', $family->id)
            ->assertJsonPath('older_adult.family_caregiver.email', 'laura.rodriguez@example.com')
            ->assertJsonPath('older_adult.family_caregiver.phone', '5555-0104')
            ->assertJsonPath('older_adult.professional_caregiver_id', $professional->id)
            ->assertJsonPath('older_adult.professional_caregiver.name', 'Maria Gonzalez')
            ->assertJsonPath('older_adult.professional_caregiver.location', 'Zona 10')
            ->assertJsonPath('older_adult.emergency_contact_name', 'Carolina Martinez')
            ->assertJsonPath('older_adult.medical_history', 'Hipertension controlada.')
            ->assertJsonPath('older_adult.medications.0.medication_id', $medication->id)
            ->assertJsonPath('older_adult.medications.0.name', 'Losartan')
            ->assertJsonPath('older_adult.medications.0.start_date', '2026-04-01')
            ->assertJsonPath('older_adult.medications.0.is_active', true)
            ->assertJsonPath('older_adult.incidents_count', 1)
            ->assertJsonPath('older_adult.incidents.0.title', 'Revision de presion')
            ->assertJsonPath('older_adult.incidents.0.older_adult.id', $assignedAdult->id)
            ->assertJsonPath('older_adult.incidents.0.reporter.name', 'Maria Gonzalez');

        $this->getJson("/api/family/older-adults/{$otherAdult->id}")
            ->assertForbidden();
    }

    public function test_family_incidents_endpoint_returns_only_assigned_complete_incidents(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00'));

        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $otherFamily = User::factory()->create([
            'name' => 'Ana Lopez',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'name' => 'Maria Gonzalez',
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $assignedAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => 'Laura Rodriguez',
            'family_caregiver_id' => $family->id,
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $otherAdult = OlderAdult::create([
            'full_name' => 'Elena Castillo',
            'age' => 88,
            'room' => 'C-305',
            'status' => 'Critico',
            'caregiver_family' => 'Ana Lopez',
            'family_caregiver_id' => $otherFamily->id,
            'created_by' => $professional->id,
        ]);

        Incident::create([
            'title' => 'Revision de presion',
            'description' => 'Se notifico lectura elevada.',
            'adult_name' => 'Rosa Martinez',
            'older_adult_id' => $assignedAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        Incident::create([
            'title' => 'Molestia respiratoria',
            'description' => 'Se monitoreo saturacion.',
            'adult_name' => 'Elena Castillo',
            'older_adult_id' => $otherAdult->id,
            'severity' => 'alta',
            'status' => 'abierto',
            'incident_date' => Carbon::today()->toDateString(),
            'incident_time' => '09:30:00',
            'reported_by' => $professional->id,
        ]);

        Sanctum::actingAs($family);

        $this->getJson('/api/family/incidents?date=2026-05-01')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.open', 1)
            ->assertJsonPath('incidents.0.title', 'Revision de presion')
            ->assertJsonPath('incidents.0.older_adult.id', $assignedAdult->id)
            ->assertJsonPath('incidents.0.older_adult.full_name', 'Rosa Martinez')
            ->assertJsonPath('incidents.0.reporter.name', 'Maria Gonzalez')
            ->assertJsonMissing(['title' => 'Molestia respiratoria']);

        $this->getJson("/api/family/incidents?date=2026-05-01&older_adult_id={$otherAdult->id}")
            ->assertForbidden();
    }

    public function test_admin_assigns_older_adult_to_real_approved_family_caregiver(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 09:00:00'));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $approvedFamily = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $pendingFamily = User::factory()->create([
            'name' => 'Jose Perez',
            'role' => 'familiar',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/family-caregivers')
            ->assertOk()
            ->assertJsonFragment(['id' => $approvedFamily->id, 'name' => 'Laura Rodriguez'])
            ->assertJsonMissing(['id' => $pendingFamily->id, 'name' => 'Jose Perez']);

        $createResponse = $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'family_caregiver_id' => $approvedFamily->id,
            'medications' => [
                [
                    'name' => 'Losartan',
                    'dosage' => '1 tableta',
                    'schedule' => '8:00 AM',
                    'days' => ['jueves'],
                    'notes' => 'Administrar despues del desayuno.',
                ],
            ],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('older_adult.family_caregiver_id', $approvedFamily->id)
            ->assertJsonPath('older_adult.family_caregiver_name', 'Laura Rodriguez')
            ->assertJsonPath('older_adult.caregiver_family', 'Laura Rodriguez');

        Sanctum::actingAs($approvedFamily);

        $this->getJson('/api/family/routines')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('routine.0.older_adult_name', 'Rosa Martinez')
            ->assertJsonPath('routine.0.medication_name', 'Losartan');
    }

    public function test_admin_cannot_assign_pending_family_caregiver_to_older_adult(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $pendingFamily = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Miguel Herrera',
            'family_caregiver_id' => $pendingFamily->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['family_caregiver_id']);
    }

    public function test_family_profile_name_update_keeps_assignments_visible(): void
    {
        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'email' => 'laura@example.com',
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => 'Laura Rodriguez',
            'created_by' => $family->id,
        ]);

        Sanctum::actingAs($family);

        $this->putJson('/api/me', [
            'name' => 'Laura Ramirez',
            'email' => 'laura.ramirez@example.com',
            'location' => 'Guatemala',
            'phone' => '5555-9999',
            'birthdate' => null,
        ])->assertOk()
            ->assertJsonPath('user.name', 'Laura Ramirez');

        $this->assertSame('Laura Ramirez', $olderAdult->refresh()->caregiver_family);

        $this->getJson('/api/family/overview')
            ->assertOk()
            ->assertJsonPath('stats.older_adults', 1)
            ->assertJsonPath('older_adults.0.full_name', 'Rosa Martinez');
    }
}

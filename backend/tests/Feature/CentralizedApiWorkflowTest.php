<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\Rutina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CentralizedApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'secret123';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_login_and_core_modules_return_json_for_centralized_helper(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 09:00:00'));

        $context = $this->makeCareContext();
        $token = $this->loginAndGetToken($context['admin']->email);

        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
        $this->withToken($token)->getJson('/api/admin/older-adults')->assertOk();
        $this->withToken($token)->getJson("/api/admin/older-adults/{$context['olderAdult']->id}")->assertOk();
        $this->withToken($token)->getJson('/api/admin/schedules')->assertOk();
        $this->withToken($token)->getJson('/api/admin/vacation-requests')->assertOk();
        $this->withToken($token)->getJson('/api/admin/medication-statistics?filter=day')->assertOk();
        $this->withToken($token)->getJson("/api/rutinas?older_adult_id={$context['olderAdult']->id}")
            ->assertOk()
            ->assertJsonPath('rutinas.0.nombre', 'Rutina matutina');
        $this->withToken($token)->getJson('/api/incidents?date=2026-05-23')
            ->assertOk()
            ->assertJsonPath('incidents.0.title', 'Revision de presion');
    }

    public function test_professional_login_and_modules_return_json_for_centralized_helper(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 09:00:00'));

        $context = $this->makeCareContext();
        $token = $this->loginAndGetToken($context['professional']->email);

        $this->withToken($token)->getJson('/api/professional/overview')->assertOk();
        $this->withToken($token)->getJson('/api/professional/older-adults')
            ->assertOk()
            ->assertJsonPath('older_adults.0.full_name', 'Rosa Martinez');
        $this->withToken($token)->getJson("/api/professional/routines?older_adult_id={$context['olderAdult']->id}")->assertOk();
        $this->withToken($token)->getJson("/api/rutinas?older_adult_id={$context['olderAdult']->id}")
            ->assertOk()
            ->assertJsonPath('rutinas.0.nombre', 'Rutina matutina');
        $this->withToken($token)->getJson('/api/professional/schedules')
            ->assertOk()
            ->assertJsonPath('schedules.0.day_of_week', 6);
        $this->withToken($token)->getJson('/api/professional/vacation-requests')->assertOk();
    }

    public function test_family_login_and_modules_return_json_for_centralized_helper(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 09:00:00'));

        $context = $this->makeCareContext();
        $token = $this->loginAndGetToken($context['family']->email);

        $this->withToken($token)->getJson('/api/family/overview')->assertOk();
        $this->withToken($token)->getJson('/api/family/older-adults')
            ->assertOk()
            ->assertJsonPath('older_adults.0.full_name', 'Rosa Martinez');
        $this->withToken($token)->getJson("/api/family/older-adults/{$context['olderAdult']->id}")->assertOk();
        $this->withToken($token)->getJson("/api/family/routines?older_adult_id={$context['olderAdult']->id}")
            ->assertOk()
            ->assertJsonPath('routine.0.older_adult_name', 'Rosa Martinez');
        $this->withToken($token)->getJson("/api/family/older-adults/{$context['olderAdult']->id}/incidents?date=2026-05-23")
            ->assertOk()
            ->assertJsonPath('incidents.0.title', 'Revision de presion');
    }

    private function loginAndGetToken(string $email): string
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $this->password,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login exitoso')
            ->json('token');
    }

    /**
     * @return array{admin: User, professional: User, family: User, olderAdult: OlderAdult}
     */
    private function makeCareContext(): array
    {
        $admin = User::factory()->create([
            'password' => $this->password,
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'name' => 'Maria Gonzalez',
            'password' => $this->password,
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $family = User::factory()->create([
            'name' => 'Laura Rodriguez',
            'password' => $this->password,
            'role' => 'familiar',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'age' => 81,
            'birthdate' => '1944-02-12',
            'room' => 'A-101',
            'status' => 'Estable',
            'caregiver_family' => $family->name,
            'family_caregiver_id' => $family->id,
            'professional_caregiver_id' => $professional->id,
            'created_by' => $admin->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'quantity' => 40,
            'unit' => 'tabletas',
            'minimum_stock' => 10,
            'expiration_date' => '2026-12-01',
            'is_active' => true,
        ]);

        OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'schedule' => '08:00',
            'days' => ['sabado'],
            'is_active' => true,
        ]);

        Rutina::create([
            'older_adult_id' => $olderAdult->id,
            'created_by' => $admin->id,
            'nombre' => 'Rutina matutina',
            'horario' => '08:00',
            'actividades' => ['Tomar signos vitales'],
        ]);

        CaregiverSchedule::create([
            'user_id' => $professional->id,
            'day_of_week' => 6,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'notes' => 'Turno de prueba',
        ]);

        Incident::create([
            'title' => 'Revision de presion',
            'description' => 'Se notifico lectura elevada.',
            'adult_name' => $olderAdult->full_name,
            'older_adult_id' => $olderAdult->id,
            'severity' => 'media',
            'status' => 'abierto',
            'incident_date' => '2026-05-23',
            'incident_time' => '08:30:00',
            'reported_by' => $professional->id,
        ]);

        return [
            'admin' => $admin,
            'professional' => $professional,
            'family' => $family,
            'olderAdult' => $olderAdult,
        ];
    }
}

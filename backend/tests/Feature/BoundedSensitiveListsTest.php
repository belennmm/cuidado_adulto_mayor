<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\CaregiverSchedule;
use App\Models\MedicationAcquisition;
use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BoundedSensitiveListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_page_is_bounded_and_pending_filter_precedes_pagination(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->count(30)->create(['role' => 'familiar', 'is_approved' => true]);
        $pending = User::factory()->create(['name' => 'Solicitud antigua', 'role' => 'familiar', 'is_approved' => false]);
        $this->getJson('/api/admin/users')->assertOk()->assertJsonCount(25, 'users')->assertJsonPath('pagination.total', 32);
        $this->getJson('/api/admin/users?is_approved=0')->assertOk()
            ->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $pending->id)->assertJsonPath('pagination.total', 1);
        $this->getJson('/api/admin/users?q=Solicitud')->assertOk()->assertJsonPath('users.0.id', $pending->id);
    }

    public function test_adult_pages_are_stable_and_search_covers_records_outside_the_first_page(): void
    {
        Sanctum::actingAs($this->admin());
        for ($index = 0; $index < 30; $index++) {
            OlderAdult::create(['full_name' => sprintf('Adulto %02d', $index)]);
        }
        $first = $this->getJson('/api/admin/older-adults')->assertOk()->assertJsonCount(25, 'older_adults');
        $second = $this->getJson('/api/admin/older-adults?page=2')->assertOk()->assertJsonCount(5, 'older_adults');
        $this->assertSame([], array_intersect(array_column($first->json('older_adults'), 'id'), array_column($second->json('older_adults'), 'id')));
        $this->getJson('/api/admin/older-adults?q=29')->assertOk()->assertJsonCount(1, 'older_adults')
            ->assertJsonPath('older_adults.0.full_name', 'Adulto 29');
        $this->getJson('/api/admin/older-adults?page=3')->assertOk()->assertJsonCount(0, 'older_adults')->assertJsonPath('pagination.total', 30);
    }

    #[DataProvider('invalidPagination')]
    public function test_pagination_limits_and_types_are_validated(string $query, string $field): void
    {
        Sanctum::actingAs($this->admin());
        foreach (['/api/admin/users', '/api/admin/older-adults', '/api/incidents', '/api/admin/medications/inventory'] as $path) {
            $this->getJson($path.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public static function invalidPagination(): array
    {
        return [['per_page=101', 'per_page'], ['per_page=0', 'per_page'], ['page=0', 'page'], ['page=10001', 'page'], ['page[]=1', 'page'], ['per_page=all', 'per_page']];
    }

    #[DataProvider('caregivers')]
    public function test_assignment_is_applied_before_paging_and_counting(string $role, string $module, string $assignment): void
    {
        $actor = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $other = User::factory()->create(['role' => $role, 'is_approved' => true]);
        foreach ([$actor, $other] as $owner) {
            for ($index = 0; $index < 30; $index++) {
                $adult = OlderAdult::create(['full_name' => 'Adulto '.$index, $assignment => $owner->id]);
                Incident::create(['title' => 'Incidente '.$index, 'older_adult_id' => $adult->id, 'reported_by' => $owner->id,
                    'incident_date' => now(config('app.timezone'))->toDateString(), 'status' => 'abierto']);
            }
        }
        Sanctum::actingAs($actor);
        $adults = $this->getJson('/api/'.$module.'/older-adults?page=2')->assertOk()
            ->assertJsonCount(5, 'older_adults')->assertJsonPath('pagination.total', 30)->json('older_adults');
        foreach ($adults as $adult) {
            $this->assertSame($actor->id, $adult[$assignment]);
        }
        $incidents = $this->getJson('/api/incidents?page=2')->assertOk()
            ->assertJsonCount(5, 'incidents')->assertJsonPath('pagination.total', 30)->json('incidents');
        foreach ($incidents as $incident) {
            $this->assertSame($actor->id, OlderAdult::findOrFail($incident['older_adult_id'])->{$assignment});
        }
        if ($module === 'family') {
            $this->getJson('/api/family/incidents?page=2')->assertOk()
                ->assertJsonCount(5, 'incidents')->assertJsonPath('summary.total', 30)->assertJsonPath('summary.open', 30);
        }
    }

    public static function caregivers(): array
    {
        return [['familiar', 'family', 'family_caregiver_id'], ['profesional', 'professional', 'professional_caregiver_id']];
    }

    public function test_consolidated_inventory_counts_all_assignments_before_paging_groups(): void
    {
        Sanctum::actingAs($this->admin());
        $adult = OlderAdult::create(['full_name' => 'Paciente']);
        $medication = Medication::create(['name' => 'Medicamento agregado', 'is_active' => true]);
        for ($index = 0; $index < 30; $index++) {
            OlderAdultMedication::create(['medication_id' => $medication->id,
                'older_adult_id' => $index < 15 ? $adult->id : null, 'quantity' => 2, 'minimum_stock' => 1, 'unit' => 'tabletas']);
        }
        $other = Medication::create(['name' => 'Otro medicamento', 'is_active' => true]);
        OlderAdultMedication::create(['medication_id' => $other->id, 'quantity' => 3, 'unit' => 'tabletas']);
        $this->getJson('/api/admin/medications/inventory?scope=general&per_page=1')->assertOk()
            ->assertJsonCount(1, 'inventory')->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('inventory.0.quantity', 60)->assertJsonPath('inventory.0.minimum_stock', 30)
            ->assertJsonPath('inventory.0.assigned_quantity', 30)->assertJsonPath('inventory.0.unassigned_quantity', 30);
        $this->getJson('/api/admin/medications/inventory?scope=general&per_page=1&page=2')->assertOk()
            ->assertJsonPath('inventory.0.medication_id', $other->id);
        $this->getJson('/api/admin/medications/inventory?scope=unassigned&per_page=1')->assertOk()
            ->assertJsonPath('pagination.total', 16)->assertJsonPath('inventory.0.older_adult_id', null);
        $this->getJson('/api/admin/medications/inventory?older_adult_id='.$adult->id.'&per_page=1')->assertOk()
            ->assertJsonPath('pagination.total', 15)->assertJsonPath('inventory.0.older_adult_id', $adult->id);
    }

    public function test_calendar_has_a_bounded_window_and_independent_event_pages(): void
    {
        Sanctum::actingAs($this->admin());
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        OlderAdult::create(['full_name' => 'Paciente', 'professional_caregiver_id' => $professional->id]);
        CaregiverSchedule::create(['user_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '16:00']);
        $this->getJson('/api/admin/schedules/calendar?start_date=2026-10-01&end_date=2026-10-31&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'shifts')->assertJsonCount(1, 'events')
            ->assertJsonPath('pagination.total', 4)->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('events_pagination.total', 4)->assertJsonPath('events_pagination.current_page', 1);
        $this->getJson('/api/admin/schedules/calendar?start_date=2026-10-01&end_date=2026-11-12')
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_medication_ranking_keeps_global_totals_and_leader_on_later_pages(): void
    {
        Sanctum::actingAs($this->admin());
        $leader = null;
        foreach ([30, 20, 10] as $quantity) {
            $medication = Medication::create(['name' => 'Medicamento '.$quantity, 'is_active' => true]);
            $leader ??= $medication;
            MedicationAcquisition::create(['medication_id' => $medication->id, 'quantity' => $quantity, 'acquired_at' => now(config('app.timezone'))]);
        }
        $this->getJson('/api/admin/medication-statistics?filter=day&per_page=1&page=2')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('items.0.unitsAcquired', 20)->assertJsonPath('leader.id', (string) $leader->id)
            ->assertJsonPath('leader.unitsAcquired', 30);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_approved' => true]);
    }
}

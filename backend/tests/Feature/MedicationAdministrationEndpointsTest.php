<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicationAdministrationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_mark_assigned_medication_as_taken(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:15:00'));

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
            'schedule' => '8:00 AM',
            'is_active' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson("/api/medications/{$assignment->id}/taken", [
            'administration_time' => '10:00',
            'notes' => 'Administrado sin novedad',
        ])
            ->assertOk()
            ->assertJsonPath('administration.older_adult_medication_id', $assignment->id)
            ->assertJsonPath('administration.administration_date', '2026-05-04')
            ->assertJsonPath('administration.administration_time', '10:00:00');

        $this->assertDatabaseHas('medication_administrations', [
            'older_adult_medication_id' => $assignment->id,
            'administration_type' => 'scheduled',
            'administration_date' => '2026-05-04',
        ]);
    }

    public function test_mark_taken_is_idempotent_for_same_day_and_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:15:00'));

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
            'is_active' => true,
        ]);

        Sanctum::actingAs($professional);

        $firstId = $this->postJson("/api/medications/{$assignment->id}/taken", [
            'administration_time' => '10:00',
        ])->assertOk()->json('administration.id');

        $this->postJson("/api/medications/{$assignment->id}/taken", [
            'administration_time' => '10:30',
        ])->assertOk()->assertJsonPath('administration.id', $firstId);

        $this->assertSame(
            1,
            MedicationAdministration::query()
                ->where('older_adult_medication_id', $assignment->id)
                ->where('administration_type', 'scheduled')
                ->whereDate('administration_date', '2026-05-04')
                ->count()
        );
    }

    public function test_database_rejects_two_simultaneous_writers_for_the_same_dose(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:15:00'));

        [$professional, $assignment] = $this->assignedMedication();

        $attributes = [
            'older_adult_id' => $assignment->older_adult_id,
            'older_adult_medication_id' => $assignment->id,
            'medication_id' => $assignment->medication_id,
            'administration_type' => 'scheduled',
            'administration_date' => '2026-05-04',
            'administration_time' => '10:00:00',
            'recorded_by' => $professional->id,
        ];

        MedicationAdministration::create($attributes);

        try {
            MedicationAdministration::create([
                ...$attributes,
                'administration_time' => '10:00:01',
            ]);
            $this->fail('La base de datos permitio una dosis programada duplicada.');
        } catch (QueryException) {
            $this->assertDatabaseCount('medication_administrations', 1);
        }
    }

    public function test_database_interruption_does_not_leave_partial_medication_record(): void
    {
        [$professional, $assignment] = $this->assignedMedication();
        Sanctum::actingAs($professional);

        DB::statement('PRAGMA query_only = ON');

        try {
            $this->postJson("/api/medications/{$assignment->id}/taken")
                ->assertServerError();
        } finally {
            DB::statement('PRAGMA query_only = OFF');
        }

        $this->assertDatabaseCount('medication_administrations', 0);
    }

    public function test_pending_professional_cannot_mark_taken(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
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
            'is_active' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson("/api/medications/{$assignment->id}/taken")
            ->assertForbidden();
    }

    public function test_professional_cannot_mark_other_professionals_assignment(): void
    {
        $owner = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $other = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'professional_caregiver_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $medication = Medication::create([
            'name' => 'Losartan',
            'is_active' => true,
        ]);

        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/medications/{$assignment->id}/taken")
            ->assertForbidden();
    }

    private function assignedMedication(): array
    {
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
        $medication = Medication::create(['name' => 'Losartan', 'is_active' => true]);
        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'dosage' => '1 tableta',
            'is_active' => true,
        ]);

        return [$professional, $assignment];
    }
}


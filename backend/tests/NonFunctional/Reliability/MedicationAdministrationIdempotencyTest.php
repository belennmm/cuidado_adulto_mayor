<?php

namespace Tests\NonFunctional\Reliability;

use App\Models\MedicationAdministration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class MedicationAdministrationIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_retry_returns_original_administration_without_creating_or_overwriting_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:15:00'));

        $professional = $this->createApprovedProfessional();
        $assignment = $this->createMedicationAssignment($this->createAssignedOlderAdult($professional));

        Sanctum::actingAs($professional);

        $firstResponse = $this->postJson("/api/medications/{$assignment->id}/taken", [
            'administration_time' => '10:00',
            'notes' => 'Registro original',
        ])->assertOk();

        $retryResponse = $this->postJson("/api/medications/{$assignment->id}/taken", [
            'administration_time' => '10:30',
            'notes' => 'Reintento tardio',
        ])->assertOk();

        $this->assertSame(
            $firstResponse->json('administration.id'),
            $retryResponse->json('administration.id'),
        );

        $retryResponse
            ->assertJsonPath('administration.administration_time', '10:00:00')
            ->assertJsonPath('administration.notes', 'Registro original');

        $this->assertSame(
            1,
            MedicationAdministration::query()
                ->where('older_adult_medication_id', $assignment->id)
                ->where('administration_type', 'scheduled')
                ->whereDate('administration_date', '2026-05-04')
                ->count(),
        );
    }

    public function test_database_constraint_rejects_a_duplicate_scheduled_administration(): void
    {
        $professional = $this->createApprovedProfessional();
        $assignment = $this->createMedicationAssignment($this->createAssignedOlderAdult($professional));
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

        $this->expectException(QueryException::class);

        MedicationAdministration::create([
            ...$attributes,
            'administration_time' => '10:01:00',
        ]);
    }
}

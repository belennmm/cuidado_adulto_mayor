<?php

namespace Tests\NonFunctional\Reliability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\Concerns\SimulatesDatabaseWriteFailure;
use Tests\TestCase;

class IncidentPersistenceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;
    use SimulatesDatabaseWriteFailure;

    public function test_database_interruption_does_not_leave_a_partial_incident(): void
    {
        $professional = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Rosa Martinez']);

        Sanctum::actingAs($professional);
        $this->withDatabaseWritesDisabled(function () use ($olderAdult): void {
            $this->postJson('/api/professional/incidents', [
                'older_adult_id' => $olderAdult->id,
                'title' => 'Caida durante corte de base de datos',
            ])->assertServerError();
        });

        $this->assertDatabaseCount('incidents', 0);
    }
}

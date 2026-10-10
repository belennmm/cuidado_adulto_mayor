<?php

namespace Tests\NonFunctional\Reliability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class IncidentPersistenceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    public function test_database_interruption_does_not_leave_a_partial_incident(): void
    {
        $professional = $this->createApprovedProfessional();
        $olderAdult = $this->createAssignedOlderAdult($professional, ['full_name' => 'Rosa Martinez']);

        Sanctum::actingAs($professional);
        DB::statement('PRAGMA query_only = ON');

        try {
            $this->postJson('/api/professional/incidents', [
                'older_adult_id' => $olderAdult->id,
                'title' => 'Caida durante corte de base de datos',
            ])->assertServerError();
        } finally {
            DB::statement('PRAGMA query_only = OFF');
        }

        $this->assertDatabaseCount('incidents', 0);
    }
}

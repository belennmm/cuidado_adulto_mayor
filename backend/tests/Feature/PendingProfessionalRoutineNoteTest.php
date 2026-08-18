<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingProfessionalRoutineNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $pendingProfessional;

    private OlderAdult $olderAdult;

    protected function setUp(): void
    {
        parent::setUp();

        // SCRUM-611: crear un profesional no aprobado.
        $this->pendingProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]);

        $this->olderAdult = OlderAdult::create([
            'full_name' => 'Adulto de prueba',
            'professional_caregiver_id' => $this->pendingProfessional->id,
            'created_by' => $this->pendingProfessional->id,
        ]);

        Sanctum::actingAs($this->pendingProfessional);
    }
}

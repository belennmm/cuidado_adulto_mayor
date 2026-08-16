<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteCreatePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_prepare_routine_note_create_payload(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martinez',
            'room' => 'A-101',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        Sanctum::actingAs($professional);

        $payload = [
            'older_adult_id' => $olderAdult->id,
            'content' => 'Nota de seguimiento del turno de la manana.',
        ];

        $this->assertAuthenticatedAs($professional, 'sanctum');
        $this->assertSame($olderAdult->id, $payload['older_adult_id']);
        $this->assertSame('Nota de seguimiento del turno de la manana.', $payload['content']);
        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
        ]);
        $this->assertDatabaseCount('routine_notes', 0);
    }
}

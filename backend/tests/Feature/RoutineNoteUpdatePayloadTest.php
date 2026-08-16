<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteUpdatePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_prepare_routine_note_update_payload(): void
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

        $note = RoutineNote::create([
            'older_adult_id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Contenido original',
            'note_date' => '2026-08-16',
        ]);

        Sanctum::actingAs($professional);

        $payload = [
            'content' => 'Contenido actualizado para el turno de la manana.',
        ];

        $this->assertAuthenticatedAs($professional, 'sanctum');
        $this->assertSame($professional->id, $note->professional_caregiver_id);
        $this->assertSame($olderAdult->id, $note->older_adult_id);
        $this->assertSame('Contenido actualizado para el turno de la manana.', $payload['content']);
        $this->assertDatabaseHas('routine_notes', [
            'id' => $note->id,
            'content' => 'Contenido original',
        ]);
    }
}

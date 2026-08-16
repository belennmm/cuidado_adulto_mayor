<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_update_own_routine_note(): void
    {
        $professional = User::factory()->create([
            'name' => 'Cuidadora Profesional',
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

        $this->putJson("/api/professional/routine-notes/{$note->id}", [
            'content' => 'Contenido actualizado para el turno de la manana.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Nota actualizada correctamente.')
            ->assertJsonPath('note.id', $note->id)
            ->assertJsonPath('note.older_adult_id', $olderAdult->id)
            ->assertJsonPath('note.content', 'Contenido actualizado para el turno de la manana.')
            ->assertJsonPath('note.note_date', '2026-08-16')
            ->assertJsonPath('note.professional_caregiver.id', $professional->id)
            ->assertJsonPath('note.professional_caregiver.name', 'Cuidadora Profesional');

        $this->assertDatabaseHas('routine_notes', [
            'id' => $note->id,
            'older_adult_id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Contenido actualizado para el turno de la manana.',
            'note_date' => '2026-08-16 00:00:00',
        ]);

        $this->assertDatabaseMissing('routine_notes', [
            'id' => $note->id,
            'content' => 'Contenido original',
        ]);
    }
}

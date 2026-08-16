<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_create_routine_note(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 09:00:00'));

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

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/routine-notes', [
            'older_adult_id' => $olderAdult->id,
            'content' => 'Nota de seguimiento del turno de la manana.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Nota guardada correctamente.')
            ->assertJsonPath('note.older_adult_id', $olderAdult->id)
            ->assertJsonPath('note.content', 'Nota de seguimiento del turno de la manana.')
            ->assertJsonPath('note.note_date', '2026-08-16')
            ->assertJsonPath('note.professional_caregiver.id', $professional->id)
            ->assertJsonPath('note.professional_caregiver.name', 'Cuidadora Profesional');

        $this->assertDatabaseHas('routine_notes', [
            'older_adult_id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Nota de seguimiento del turno de la manana.',
            'note_date' => '2026-08-16 00:00:00',
        ]);
    }
}

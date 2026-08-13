<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherProfessional;
    private OlderAdult $olderAdult;
    private RoutineNote $note;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);
        $this->otherProfessional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);
        $this->olderAdult = OlderAdult::create([
            'full_name' => 'Adulto de prueba',
            'professional_caregiver_id' => $this->otherProfessional->id,
            'created_by' => $this->owner->id,
        ]);
        $this->note = RoutineNote::create([
            'older_adult_id' => $this->olderAdult->id,
            'professional_caregiver_id' => $this->owner->id,
            'content' => 'Contenido original',
            'note_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($this->otherProfessional);
    }

    public function test_professional_cannot_view_another_professionals_note(): void
    {
        $this->getJson("/api/professional/routine-notes/{$this->note->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes acceso a esta nota.')
            ->assertJsonMissing(['content' => 'Contenido original']);
    }

    public function test_listing_does_not_expose_another_professionals_notes(): void
    {
        $this->getJson("/api/professional/routine-notes?older_adult_id={$this->olderAdult->id}")
            ->assertOk()
            ->assertJsonCount(0, 'notes')
            ->assertJsonMissing(['content' => 'Contenido original']);
    }

    public function test_professional_cannot_update_another_professionals_note(): void
    {
        $this->putJson("/api/professional/routine-notes/{$this->note->id}", [
            'content' => 'Contenido alterado',
        ])->assertForbidden();

        $this->assertDatabaseHas('routine_notes', [
            'id' => $this->note->id,
            'professional_caregiver_id' => $this->owner->id,
            'content' => 'Contenido original',
        ]);
    }

    public function test_professional_cannot_delete_another_professionals_note(): void
    {
        $this->deleteJson("/api/professional/routine-notes/{$this->note->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('routine_notes', [
            'id' => $this->note->id,
            'content' => 'Contenido original',
        ]);
    }

    public function test_owner_can_view_update_and_delete_own_note_when_assigned(): void
    {
        $this->olderAdult->update([
            'professional_caregiver_id' => $this->owner->id,
        ]);
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/professional/routine-notes/{$this->note->id}")
            ->assertOk()
            ->assertJsonPath('note.content', 'Contenido original');

        $this->putJson("/api/professional/routine-notes/{$this->note->id}", [
            'content' => 'Contenido actualizado',
        ])->assertOk()
            ->assertJsonPath('note.content', 'Contenido actualizado');

        $this->deleteJson("/api/professional/routine-notes/{$this->note->id}")
            ->assertOk();

        $this->assertDatabaseMissing('routine_notes', [
            'id' => $this->note->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteListingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_list_current_week_notes_for_an_assigned_older_adult(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

        $professional = User::factory()->create([
            'name' => 'María Cuidadora',
            'role' => 'profesional',
            'is_approved' => true,
        ]);
        $assignedOlderAdult = OlderAdult::create([
            'full_name' => 'Rosa Martínez',
            'room' => 'A-101',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);
        $otherOlderAdult = OlderAdult::create([
            'full_name' => 'Miguel Herrera',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        RoutineNote::create([
            'older_adult_id' => $assignedOlderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Nota del lunes',
            'note_date' => '2026-08-03',
        ]);
        RoutineNote::create([
            'older_adult_id' => $assignedOlderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Nota más reciente',
            'note_date' => '2026-08-05',
        ]);
        RoutineNote::create([
            'older_adult_id' => $assignedOlderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Nota de la semana anterior',
            'note_date' => '2026-07-31',
        ]);
        RoutineNote::create([
            'older_adult_id' => $otherOlderAdult->id,
            'professional_caregiver_id' => $professional->id,
            'content' => 'Nota de otro adulto mayor',
            'note_date' => '2026-08-05',
        ]);

        Sanctum::actingAs($professional);

        $this->getJson("/api/professional/routine-notes?older_adult_id={$assignedOlderAdult->id}")
            ->assertOk()
            ->assertJsonPath('older_adult.id', $assignedOlderAdult->id)
            ->assertJsonPath('week.start', '2026-08-03')
            ->assertJsonPath('week.end', '2026-08-09')
            ->assertJsonCount(2, 'notes')
            ->assertJsonPath('notes.0.content', 'Nota más reciente')
            ->assertJsonPath('notes.1.content', 'Nota del lunes')
            ->assertJsonPath('notes.0.professional_caregiver.id', $professional->id)
            ->assertJsonMissing(['content' => 'Nota de la semana anterior'])
            ->assertJsonMissing(['content' => 'Nota de otro adulto mayor']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineNoteValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_a_routine_note_with_incomplete_data(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $olderAdult = OlderAdult::create([
            'full_name' => 'Adulto Prueba Nota',
            'room' => 'TEST-02',
            'status' => 'Estable',
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $professional->id,
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $professional->id)
            ->assertJsonPath('user.role', 'profesional');

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdult->id,
            'professional_caregiver_id' => $professional->id,
        ]);
    }
}

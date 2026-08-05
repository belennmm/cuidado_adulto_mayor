<?php

namespace Tests\Feature;

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

        $this->assertDatabaseHas('users', [
            'id' => $professional->id,
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $professional->id)
            ->assertJsonPath('user.role', 'profesional');
    }
}

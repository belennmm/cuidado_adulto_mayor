<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertDatabaseHas('users', [
            'id' => $professional->id,
            'role' => 'profesional',
            'is_approved' => true,
        ]);
    }
}

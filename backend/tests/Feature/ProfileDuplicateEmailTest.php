<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDuplicateEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_rejects_an_email_used_by_another_user(): void
    {
        $firstUser = User::factory()->create([
            'name' => 'Ana Cuidadora',
            'email' => 'ana@example.com',
            'is_approved' => true,
        ]);

        $secondUser = User::factory()->create([
            'name' => 'Carlos Cuidador',
            'email' => 'carlos@example.com',
            'is_approved' => true,
        ]);

        // Paso 2: intentar asignar al primer usuario el correo del segundo.
    }
}

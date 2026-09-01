<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

        Sanctum::actingAs($firstUser);

        $response = $this->putJson('/api/me', [
            'email' => $secondUser->email,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $firstUser->refresh();

        $this->assertSame('ana@example.com', $firstUser->email);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileUpdatePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_allowed_profile_fields_and_changes_are_persisted(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Cuidadora',
            'email' => 'ana@example.com',
            'phone' => '55551234',
            'location' => 'Zona 1',
            'birthdate' => '1980-05-10',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Ana López',
            'email' => 'ana.lopez@example.com',
            'phone' => '55559876',
            'location' => 'Zona 10',
            'birthdate' => '1982-11-22',
        ];

        $this->putJson('/api/me', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado correctamente.')
            ->assertJsonPath('user.name', $payload['name'])
            ->assertJsonPath('user.email', $payload['email'])
            ->assertJsonPath('user.phone', $payload['phone'])
            ->assertJsonPath('user.location', $payload['location'])
            ->assertJsonPath('user.birthdate', $payload['birthdate']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'location' => $payload['location'],
        ]);

        $this->assertSame(
            $payload['birthdate'],
            $user->fresh()->birthdate?->toDateString()
        );
    }
}

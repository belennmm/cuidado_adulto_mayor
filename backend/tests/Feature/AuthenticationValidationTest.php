<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_reject_empty_and_malformed_required_fields_without_creating_a_user(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->postJson('/api/login', [
            'email' => 'correo-invalido',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);

        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $invalidPayload = [
            'name' => [],
            'email' => 'correo-invalido',
            'password' => 'corta',
            'role' => 'rol-invalido',
        ];

        $this->postJson('/api/register', $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['email' => $invalidPayload['email']]);
    }
}

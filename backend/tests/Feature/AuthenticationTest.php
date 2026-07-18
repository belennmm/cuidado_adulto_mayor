<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_user_can_login_and_receives_a_sanctum_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
            'is_approved' => true,
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('message', 'Login exitoso')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['is_approved' => true]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales invalidas');
    }

    public function test_pending_non_admin_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_approved' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu cuenta esta pendiente de aprobacion por un administrador.');
    }

    public function test_registration_creates_a_pending_user_and_normalizes_role(): void
    {
        $payload = [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
            'role' => 'cuidador_profesional',
            'phone' => fake()->numerify('########'),
        ];

        $this->postJson('/api/register', $payload)
            ->assertCreated()
            ->assertJsonPath('user.email', $payload['email'])
            ->assertJsonPath('user.role', 'profesional')
            ->assertJsonPath('user.is_approved', false);

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'role' => 'profesional',
            'is_approved' => false,
        ]);
    }

    public function test_registration_validates_required_unique_and_password_fields(): void
    {
        $existing = User::factory()->create();

        $this->postJson('/api/register', [
            'name' => '',
            'email' => $existing->email,
            'password' => 'short',
            'role' => 'invalid-role',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_authenticated_user_can_read_profile(): void
    {
        $user = User::factory()->create(['is_approved' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_guest_cannot_read_profile(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_sanctum_token(): void
    {
        $user = User::factory()->create(['is_approved' => true]);
        $token = $user->createToken('test-token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout exitoso');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

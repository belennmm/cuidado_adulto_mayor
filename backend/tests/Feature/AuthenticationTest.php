<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('login:'.sha1('rate-limit@example.com|127.0.0.1'));

        parent::tearDown();
    }

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

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'rate-limit@example.com',
            'password' => Hash::make('correct-password'),
            'is_approved' => true,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'correct-password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Demasiados intentos fallidos. Intenta nuevamente mas tarde.')
            ->assertJsonStructure(['retry_after']);
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'rate-limit@example.com',
            'password' => Hash::make('correct-password'),
            'is_approved' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'correct-password',
        ])->assertOk();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }
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
            'privacy_consent' => true,
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
            'privacy_policy_version' => '2026-09-28',
        ]);

        $this->assertNotNull(User::query()->where('email', $payload['email'])->value('privacy_consent_at'));

        $storedPassword = User::query()->where('email', $payload['email'])->value('password');
        $this->assertNotSame($payload['password'], $storedPassword);
        $this->assertTrue(Hash::check($payload['password'], $storedPassword));
    }

    public function test_registration_requires_privacy_consent(): void
    {
        $payload = [
            'name' => 'Usuario sin consentimiento',
            'email' => 'sin-consentimiento@example.com',
            'password' => 'password123',
            'role' => 'familiar',
            'privacy_consent' => false,
        ];

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['privacy_consent']);

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
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

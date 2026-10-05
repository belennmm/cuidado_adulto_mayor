<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_a_strong_hash_and_issues_an_expiring_token_without_leaking_secrets(): void
    {
        $user = User::factory()->create([
            'password' => 'Secure-Test!123',
            'is_approved' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secure-Test!123',
        ])->assertOk()->assertJsonMissing(['password' => 'Secure-Test!123']);

        $this->assertTrue(Hash::check('Secure-Test!123', $user->fresh()->password));
        $this->assertStringStartsWith('$argon2id$', $user->fresh()->password);
        $this->assertArrayNotHasKey('password', $response->json('user'));

        $token = PersonalAccessToken::query()->sole();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isBetween(now()->addMinutes(59), now()->addMinutes(61)));
    }

    public function test_password_change_immediately_revokes_all_tokens(): void
    {
        $user = User::factory()->create([
            'password' => 'Old-Secure!123',
            'is_approved' => true,
        ]);
        $token = $user->createToken('browser', ['*'], now()->addHour());

        $this->withToken($token->plainTextToken)->putJson('/api/me', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'Old-Secure!123',
            'new_password' => 'New-Secure!456',
            'new_password_confirmation' => 'New-Secure!456',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $this->getJson('https://localhost/api/ping')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Strict-Transport-Security');
    }
}

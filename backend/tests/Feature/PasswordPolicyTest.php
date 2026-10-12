<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('weakPasswords')]
    public function test_new_passwords_reject_weak_and_common_values(string $password): void
    {
        $this->assertTrue(Validator::make(['password' => $password], ['password' => PasswordPolicy::rules()])->fails());
    }

    public static function weakPasswords(): array
    {
        return [['Short-Secret!1'], ['onlylowercaseletters'], ['NOLOWERCASE!12345'], ['No-Numbers-Here!'], ['NoSymbolsHere1234'], ['PasswordPassword123!'], ['QwertyQwerty12345!'], ["Unique-Secret!2048\0"], [str_repeat('x', 129).'Aa1!']];
    }

    public function test_strong_passwords_accept_spaces_without_normalization_and_reject_reuse(): void
    {
        $password = '  Unique-Secret!2048  ';
        $this->assertTrue(Validator::make(['password' => $password], ['password' => PasswordPolicy::rules()])->passes());
        $user = User::factory()->approvedFamily()->create(['password' => $password]);
        $this->assertTrue(Validator::make(['password' => $password], ['password' => PasswordPolicy::rules($user)])->fails());
    }

    public function test_bcrypt_never_silently_truncates_new_passwords(): void
    {
        config(['hashing.driver' => 'bcrypt']);
        $this->assertTrue(Validator::make(['password' => 'Aa1!'.str_repeat('x', 69)], ['password' => PasswordPolicy::rules()])->fails());
    }

    public function test_compromised_check_sends_only_a_padded_hash_prefix_and_caches_public_range(): void
    {
        config(['passwords.check_compromised' => true]);
        $password = 'Unique-Secret!2048';
        $digest = strtoupper(sha1($password));
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/range/*' => Http::response(substr($digest, 5).":2\r\n".str_repeat('0', 35).':0')]);
        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue(Validator::make(['password' => $password], ['password' => PasswordPolicy::rules()])->fails());
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.pwnedpasswords.com/range/'.substr($digest, 0, 5)
            && $request->hasHeader('Add-Padding', 'true') && $request->body() === '');
    }

    public function test_padding_entries_with_zero_count_are_not_compromised_passwords(): void
    {
        config(['passwords.check_compromised' => true]);
        $password = 'Unique-Secret!2048';
        Http::fake(['*' => Http::response(substr(strtoupper(sha1($password)), 5).':0')]);
        $this->assertTrue(Validator::make(['password' => $password], ['password' => PasswordPolicy::rules()])->passes());
    }

    #[DataProvider('failedChecks')]
    public function test_check_failures_never_save_a_new_password(string $body, int $status): void
    {
        config(['passwords.check_compromised' => true]);
        Http::fake(['*' => Http::response($body, $status)]);
        $this->postJson('/api/register', ['name' => 'Example', 'email' => 'new@example.test', 'password' => 'Unique-Secret!2048', 'privacy_consent' => true])
            ->assertStatus(503)->assertExactJson(['message' => 'Error interno del servidor.']);
        $this->assertDatabaseCount('users', 0);
    }

    public static function failedChecks(): array
    {
        return [['unavailable', 503], ['redirect', 302], ['invalid', 200], ['', 200]];
    }

    public function test_policy_applies_to_direct_service_calls_too(): void
    {
        $this->expectException(ValidationException::class);
        app(AuthService::class)->register(['name' => 'Example', 'email' => 'new@example.test', 'password' => 'PasswordPassword123!']);
    }

    public function test_breach_service_connection_failure_never_saves_credentials(): void
    {
        config(['passwords.check_compromised' => true]);
        Http::fake(fn () => throw new ConnectionException('offline'));
        $this->postJson('/api/register', ['name' => 'Example', 'email' => 'new@example.test', 'password' => 'Unique-Secret!2048', 'privacy_consent' => true])
            ->assertStatus(503);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_policy_applies_to_admin_creation_and_profile_password_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/admin/users', ['name' => 'Example', 'email' => 'new@example.test', 'role' => 'familiar', 'password' => 'PasswordPassword123!'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/me', ['name' => $admin->name, 'email' => $admin->email, 'current_password' => 'password', 'new_password' => 'PasswordPassword123!', 'new_password_confirmation' => 'PasswordPassword123!'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('new_password');
    }
}

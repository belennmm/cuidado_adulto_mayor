<?php

namespace Tests\Feature;

use App\Jobs\SendPasswordRecovery;
use App\Models\User;
use App\Notifications\PasswordRecoveryCode as ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'Recovery-Unique!2026', 'password_confirmation' => 'Recovery-Unique!2026'];
    }

    public function test_requests_have_identical_responses_and_are_always_queued(): void
    {
        Queue::fake();
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(202);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'unknown@example.test'])->assertStatus(202);
        $this->assertSame($known->json(), $unknown->json());
        Queue::assertPushed(SendPasswordRecovery::class, 2);
        Queue::assertPushed(SendPasswordRecovery::class, fn ($job) => $job->connection === 'database');
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_job_sends_a_fixed_origin_link_without_credentials_and_stores_only_a_hash(): void
    {
        Notification::fake();
        config(['auth.password_reset_url' => 'https://frontend.example/pages/reset-password.html']);
        $user = User::factory()->create(['email' => 'MixedCase@example.test']);
        (new SendPasswordRecovery('mixedcase@example.test'))->handle();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame(config('auth.password_reset_url'), $url);
            $this->assertNull(parse_url($url, PHP_URL_QUERY));
            $this->assertNull(parse_url($url, PHP_URL_FRAGMENT));
            $this->assertContains($notification->token, $notification->toMail($user)->introLines);
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));

            return true;
        });
        (new SendPasswordRecovery('absent@example.test'))->handle();
        Notification::assertCount(1);
    }

    public function test_token_changes_password_once_without_logging_in_or_approving_the_account(): void
    {
        $user = User::factory()->create(['is_approved' => false]);
        $token = Password::broker()->createToken($user);
        $this->postJson('/api/reset-password', $this->payload($user, $token))->assertOk()->assertJsonMissingPath('token')->assertJsonMissingPath('user');
        $this->assertTrue(Hash::check('Recovery-Unique!2026', $user->fresh()->password));
        $this->assertFalse($user->fresh()->is_approved);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/reset-password', $this->payload($user, $token))->assertUnprocessable();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public static function invalidTokens(): array
    {
        return ['missing account' => ['account'], 'wrong token' => ['token'], 'expired' => ['expired'], 'exact expiration' => ['boundary']];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_recovery_is_generic_and_does_not_change_credentials(string $case): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $old = $user->password;
        $token = Password::broker()->createToken($user);
        $payload = $this->payload($user, $token);
        if ($case === 'account') {
            $payload['email'] = 'absent@example.test';
        }
        if ($case === 'token') {
            $payload['token'] = str_repeat('a', 64);
        }
        if (in_array($case, ['expired', 'boundary'], true)) {
            $this->travel($case === 'expired' ? 16 : 15)->minutes();
        }
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable()->assertExactJson([
            'code' => 'validation_failed',
            'message' => 'El enlace no es valido o ha expirado. Solicita uno nuevo.',
            'errors' => [],
        ]);
        $this->assertSame($old, $user->fresh()->password);
    }

    public function test_a_new_link_invalidates_the_old_link(): void
    {
        $user = User::factory()->create();
        $old = Password::broker()->createToken($user);
        $new = Password::broker()->createToken($user);
        $this->postJson('/api/reset-password', $this->payload($user, $old))->assertUnprocessable();
        $this->postJson('/api/reset-password', $this->payload($user, $new))->assertOk();
    }

    public function test_worker_throttles_repeated_emails_and_replaces_old_tokens_after_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $job = new SendPasswordRecovery($user->email);
        $job->handle();
        $first = Notification::sent($user, ResetPassword::class)->first()->token;
        $job->handle();
        Notification::assertCount(1);
        $this->travel(61)->seconds();
        $job->handle();
        Notification::assertCount(2);
        $this->assertFalse(Password::broker()->tokenExists($user, $first));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_reset_guessing_is_limited_without_locking_out_a_valid_login(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reset-password', $this->payload($user, str_repeat('a', 64)))->assertUnprocessable();
        }
        $this->postJson('/api/reset-password', $this->payload($user, str_repeat('b', 64)))->assertTooManyRequests();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_recovery_limits_apply_to_account_across_ips_and_to_ip_across_accounts(): void
    {
        Queue::fake();
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->postJson('/api/forgot-password', ['email' => 'rate@example.test'])->assertStatus(202);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.4'])->postJson('/api/forgot-password', ['email' => 'RATE@example.test'])->assertTooManyRequests();
        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])->postJson('/api/forgot-password', ['email' => "rate{$i}@example.test"])->assertStatus(202);
        }
        $this->postJson('/api/forgot-password', ['email' => 'another@example.test'])->assertTooManyRequests();
    }

    public function test_recovery_rejects_unknown_fields_weak_passwords_and_mismatched_confirmation(): void
    {
        Queue::fake();
        $this->postJson('/api/forgot-password', ['email' => 'test@example.test', 'role' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('role');
        Queue::assertNothingPushed();
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $data = $this->payload($user, $token);
        $data['password'] = 'weak';
        $this->postJson('/api/reset-password', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_public_login_and_registration_do_not_reveal_existing_accounts(): void
    {
        $approved = User::factory()->approvedProfessional()->create();
        $pending = User::factory()->create(['is_approved' => false]);
        $baseline = $this->postJson('/api/login', ['email' => 'absent@example.test', 'password' => 'password'])->assertUnauthorized()->json();
        foreach ([[$approved->email, 'incorrect'], [$pending->email, 'password']] as [$email, $password]) {
            $this->assertSame($baseline, $this->postJson('/api/login', compact('email', 'password'))->assertUnauthorized()->json());
        }
        $payload = ['name' => 'Public account', 'email' => 'new@example.test', 'password' => 'Registration-Unique!2026', 'role' => 'familiar', 'privacy_consent' => true];
        $new = $this->postJson('/api/register', $payload)->assertStatus(202)->assertJsonMissingPath('user')->json();
        $payload['email'] = strtoupper($approved->email);
        $this->assertSame($new, $this->postJson('/api/register', $payload)->assertStatus(202)->json());
        $this->assertSame($approved->password, $approved->fresh()->password);
        $this->assertDatabaseCount('users', 3);
    }
}

<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MfaSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function api(string $method, string $path, array $data = [], array $headers = [])
    {
        Auth::forgetGuards();

        return $this->json($method, '/api'.$path, $data, $headers);
    }

    private function enroll(): array
    {
        $user = User::factory()->approvedProfessional()->create();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
        $secret = $this->api('POST', '/mfa/setup', ['current_password' => 'password'], $headers)->assertOk()->json('secret');
        $codes = $this->api('POST', '/mfa/confirm', ['current_password' => 'password', 'code' => Totp::code($secret, intdiv(now()->timestamp, 30))], $headers)->assertOk()->json('recovery_codes');

        return [$user->fresh(), $secret, $codes, $headers];
    }

    private function challenge(User $user): string
    {
        return $this->api('POST', '/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(202)->assertJsonMissingPath('token')->assertJsonMissingPath('user')->json('challenge');
    }

    private function verify(string $challenge, string $code)
    {
        return $this->api('POST', '/mfa/verify', compact('challenge', 'code'));
    }

    public function test_totp_matches_all_sha1_rfc6238_vectors(): void
    {
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv($time, 30), 8));
        }
    }

    public function test_enrollment_encrypts_keys_hashes_codes_and_revokes_every_session(): void
    {
        [$user, $secret, $codes, $headers] = $this->enroll();
        $this->assertCount(10, array_unique($codes));
        $this->assertSame($secret, $user->mfa_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertArrayNotHasKey('mfa_secret', $user->toArray());
        $this->assertDatabaseHas('mfa_recovery_codes', ['code_hash' => hash('sha256', $codes[0])]);
        $this->assertDatabaseMissing('mfa_recovery_codes', ['code_hash' => $codes[0]]);
        $this->assertDatabaseCount('account_sessions', 0);
        $this->api('GET', '/me', [], $headers)->assertUnauthorized();
        $challenge = $this->challenge($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('mfa_challenges', ['id' => hash('sha256', $challenge)]);
        $this->verify($challenge, $codes[0])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->verify($challenge, $codes[1])->assertUnprocessable();
        $this->verify($this->challenge($user), $codes[0])->assertUnprocessable();
    }

    public function test_totp_cannot_be_replayed_and_accepts_next_time_step(): void
    {
        [$user, $secret] = $this->enroll();
        $challenge = $this->challenge($user);
        $this->verify($challenge, Totp::code($secret, intdiv(now()->timestamp, 30)))->assertUnprocessable();
        $this->travel(31)->seconds();
        $code = Totp::code($secret, intdiv(now()->timestamp, 30));
        $this->verify($challenge, $code)->assertOk();
        $this->verify($this->challenge($user), $code)->assertUnprocessable();
    }

    public function test_challenges_expire_at_five_minutes_and_replacement_invalidates_previous(): void
    {
        [$user, , $codes] = $this->enroll();
        $first = $this->challenge($user);
        $second = $this->challenge($user);
        $this->verify($first, $codes[0])->assertUnprocessable();
        $this->travel(5)->minutes();
        $this->verify($second, $codes[0])->assertUnprocessable();
        $this->assertDatabaseHas('mfa_recovery_codes', ['code_hash' => hash('sha256', $codes[0])]);
    }

    public function test_five_failed_factors_lock_the_challenge_and_account_across_ips(): void
    {
        [$user, , $codes] = $this->enroll();
        $challenge = $this->challenge($user);
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i]);
            $this->verify($challenge, str_repeat('0', 32))->assertUnprocessable();
        }
        $this->verify($challenge, $codes[0])->assertUnprocessable();
        $this->api('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
        $this->assertDatabaseHas('mfa_challenges', ['id' => hash('sha256', $challenge), 'attempts' => 5]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_recovery_codes_expire_at_seven_days(): void
    {
        [$user, , $codes] = $this->enroll();
        $this->travel(7)->days();
        $this->verify($this->challenge($user), $codes[0])->assertUnprocessable();
    }

    public function test_regeneration_invalidates_old_codes_and_all_sessions(): void
    {
        [$user, , $codes] = $this->enroll();
        $token = $this->verify($this->challenge($user), $codes[0])->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $new = $this->api('POST', '/mfa/recovery-codes', ['current_password' => 'password', 'code' => $codes[1]], $headers)->assertOk()->json('recovery_codes');
        $this->assertCount(10, $new);
        $this->assertEmpty(array_intersect($codes, $new));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->verify($this->challenge($user->fresh()), $codes[2])->assertUnprocessable();
        $this->verify($this->challenge($user->fresh()), $new[0])->assertOk();
    }

    public function test_reconfiguration_keeps_old_factor_until_confirmed_then_replaces_codes(): void
    {
        [$user, $old, $codes] = $this->enroll();
        $token = $this->verify($this->challenge($user), $codes[0])->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->api('POST', '/mfa/setup', ['current_password' => 'password'], $headers)->assertUnprocessable();
        $new = $this->api('POST', '/mfa/setup', ['current_password' => 'password', 'code' => $codes[1]], $headers)->assertOk()->json('secret');
        $this->assertSame($old, $user->fresh()->mfa_secret);
        $newCodes = $this->api('POST', '/mfa/confirm', ['current_password' => 'password', 'code' => Totp::code($new, intdiv(now()->timestamp, 30))], $headers)->assertOk()->json('recovery_codes');
        $this->assertSame($new, $user->fresh()->mfa_secret);
        $this->assertEmpty(array_intersect($codes, $newCodes));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_setup_expires_and_has_a_durable_attempt_limit(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
        $secret = $this->api('POST', '/mfa/setup', ['current_password' => 'password'], $headers)->assertOk()->json('secret');
        $this->travel(10)->minutes();
        $this->api('POST', '/mfa/confirm', ['current_password' => 'password', 'code' => Totp::code($secret, intdiv(now()->timestamp, 30))], $headers)->assertUnprocessable();
        $this->api('POST', '/mfa/setup', ['current_password' => 'password'], $headers)->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->travel(61)->seconds();
            $this->api('POST', '/mfa/confirm', ['current_password' => 'password', 'code' => str_repeat('0', 32)], $headers)->assertUnprocessable();
        }
        $this->assertSame(5, $user->fresh()->mfa_setup_attempts);
        $this->travel(301)->seconds();
        $this->api('POST', '/mfa/confirm', ['current_password' => 'password', 'code' => Totp::code($user->fresh()->mfa_pending_secret, intdiv(now()->timestamp, 30))], $headers)->assertUnprocessable();
        $this->assertNull($user->fresh()->mfa_enabled_at);
    }

    public function test_disapproved_deleted_and_pending_accounts_cannot_finish_or_renew_authentication(): void
    {
        [$user, , $codes] = $this->enroll();
        $existing = $this->verify($this->challenge($user), $codes[9])->assertOk()->json('token');
        $challenge = $this->challenge($user);
        // Direct SQL bypasses observers, so request-time checks must still reject it.
        DB::table('users')->where('id', $user->id)->update(['is_approved' => false]);
        $this->verify($challenge, $codes[0])->assertUnprocessable();
        $this->api('POST', '/reauthenticate', ['current_password' => 'password'], ['Authorization' => 'Bearer '.$existing])->assertUnauthorized();
        $this->api('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized();
        $user->refresh()->forceFill(['is_approved' => true])->save();
        $user = $user->fresh();
        $token = $this->verify($this->challenge($user), $codes[0])->assertOk()->json('token');
        $challenge = $this->challenge($user);
        $user->delete();
        $this->verify($challenge, $codes[1])->assertUnprocessable();
        $this->api('POST', '/reauthenticate', ['current_password' => 'password'], ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_password_reset_revokes_challenges_and_tokens_but_preserves_mfa(): void
    {
        [$user, $secret, $codes] = $this->enroll();
        $this->verify($this->challenge($user), $codes[0])->assertOk();
        $challenge = $this->challenge($user);
        $token = Password::broker()->createToken($user);
        $this->api('POST', '/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Recovery-Unique!2026', 'password_confirmation' => 'Recovery-Unique!2026'])->assertOk();
        $this->assertSame($secret, $user->fresh()->mfa_secret);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->verify($challenge, $codes[1])->assertUnprocessable();
        $this->api('POST', '/login', ['email' => $user->email, 'password' => 'Recovery-Unique!2026'])->assertStatus(202);
    }

    public function test_reset_token_attempt_limit_survives_route_cooldown(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $token = Password::broker()->createToken($user);
        $payload = ['email' => $user->email, 'token' => str_repeat('0', 64), 'password' => 'Recovery-Unique!2026', 'password_confirmation' => 'Recovery-Unique!2026'];
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/reset-password', $payload)->assertUnprocessable();
        }
        $this->travel(61)->seconds();
        $payload['token'] = $token;
        $this->api('POST', '/reset-password', $payload)->assertUnprocessable();
        $payload['token'] = Password::broker()->createToken($user);
        $this->api('POST', '/reset-password', $payload)->assertOk();
    }

    public function test_disable_then_reactivate_generates_new_recovery_codes(): void
    {
        [$user, , $codes] = $this->enroll();
        $token = $this->verify($this->challenge($user), $codes[0])->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->api('GET', '/mfa', [], $headers)->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('recovery_codes_remaining', 9)->assertJsonMissingPath('secret');
        $this->api('DELETE', '/mfa', ['current_password' => 'wrong', 'code' => $codes[1]], $headers)->assertUnprocessable();
        $this->api('DELETE', '/mfa', ['current_password' => 'password', 'code' => $codes[1]], $headers)->assertOk();
        $this->assertNull($user->fresh()->mfa_secret);
        $this->assertDatabaseCount('mfa_recovery_codes', 0);
        $this->assertDatabaseCount('account_sessions', 0);
        // Respect the shared limit for setup, confirmation and factor management.
        $this->travel(61)->seconds();
        $token = $this->api('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $secret = $this->api('POST', '/mfa/setup', ['current_password' => 'password'], $headers)->assertOk()->json('secret');
        $new = $this->api('POST', '/mfa/confirm', ['current_password' => 'password', 'code' => Totp::code($secret, intdiv(now()->timestamp, 30))], $headers)->assertOk()->json('recovery_codes');
        $this->assertCount(10, $new);
        $this->assertEmpty(array_intersect($codes, $new));
        $this->verify($this->challenge($user->fresh()), $codes[2])->assertUnprocessable();
        $this->verify($this->challenge($user->fresh()), $new[0])->assertOk();
    }

    public function test_token_lookup_compares_digests_and_rejects_malformed_ids(): void
    {
        $user = User::factory()->approvedProfessional()->create();
        $issued = $user->createToken('test');
        [$id, $plain] = explode('|', $issued->plainTextToken);
        $this->assertSame((int) $id, PersonalAccessToken::findToken($issued->plainTextToken)->id);
        $this->assertSame((int) $id, PersonalAccessToken::findToken($plain)->id);
        foreach (['9999999999999999999|'.$plain, 'abc|'.$plain, $id.'|wrong', '', '999999|'.$plain] as $invalid) {
            $this->assertNull(PersonalAccessToken::findToken($invalid));
        }
    }
}

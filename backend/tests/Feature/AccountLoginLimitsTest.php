<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLoginLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['abuse.login_per_minute' => 1000]);
    }

    public function test_account_limit_survives_rotating_ips_and_email_case(): void
    {
        $user = User::factory()->approvedFamily()->create(['email' => 'target@example.test']);
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)])
                ->postJson('/api/login', ['email' => $i % 2 ? strtoupper($user->email) : $user->email, 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.200'])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(301)->seconds();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_ip_limit_survives_rotating_accounts_and_success_does_not_reset_it(): void
    {
        $user = User::factory()->approvedFamily()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/login', ['email' => 'absent'.$i.'@example.test', 'password' => 'wrong'])->assertUnauthorized();
            if ($i === 15) {
                $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
            }
        }
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_pair_limit_and_minute_ip_burst_limit_are_preserved(): void
    {
        $user = User::factory()->approvedFamily()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
        config(['abuse.login_per_minute' => 1]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->postJson('/api/login', ['email' => 'absent@example.test', 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'other@example.test', 'password' => 'wrong'])->assertStatus(429);
    }
}

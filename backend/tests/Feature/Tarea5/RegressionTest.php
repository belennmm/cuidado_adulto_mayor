<?php

namespace Tests\Feature\Tarea5;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_r01_schedule_rejects_reversed_times_without_persisting(): void
    {
        $user = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        Sanctum::actingAs($user);
        $this->postJson('/api/schedules', [
            'day_of_week' => 3, 'start_time' => '16:00', 'end_time' => '08:00',
        ])->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->assertDatabaseCount('caregiver_schedules', 0);
    }

    public function test_r02_profile_update_preserves_role_in_response_and_database(): void
    {
        $user = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        Sanctum::actingAs($user);
        $this->putJson('/api/me', [
            'name' => 'Profesional T5', 'email' => $user->email, 'role' => 'admin',
        ])->assertOk()->assertJsonPath('user.role', 'profesional');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Profesional T5', 'role' => 'profesional']);
    }

    public function test_r03_pending_caregiver_cannot_login_or_receive_token(): void
    {
        $user = User::factory()->create([
            'role' => 'profesional', 'is_approved' => false, 'password' => Hash::make('secret123'),
        ]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

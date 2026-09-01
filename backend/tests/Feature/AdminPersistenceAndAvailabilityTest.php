<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPersistenceAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_changes_are_visible_across_different_admin_endpoints(): void
    {
        $adminPassword = 'admin123';
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
            'password' => Hash::make($adminPassword),
        ]);

        $userToUpdate = User::factory()->create([
            'name' => 'Original User',
            'email' => 'original@example.com',
            'location' => 'Original Location',
            'phone' => '1111111111',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => $adminPassword,
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userToUpdate->id}",
            [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'role' => 'familiar',
                'is_approved' => true,
                'location' => 'Updated Location',
                'phone' => '9999999999',
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->getJson("/api/admin/users/{$userToUpdate->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated User')
            ->assertJsonPath('user.email', 'updated@example.com')
            ->assertJsonPath('user.location', 'Updated Location')
            ->assertJsonPath('user.phone', '9999999999');

        $this->withToken($token)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'phone' => '9999999999',
            ]);
    }

    public function test_admin_remains_authenticated_after_user_modifications(): void
    {
        $adminPassword = 'admin123';
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
            'password' => Hash::make($adminPassword),
        ]);

        $userToUpdate = User::factory()->create([
            'name' => 'User One',
            'email' => 'user1@example.com',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => $adminPassword,
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userToUpdate->id}",
            [
                'name' => 'User One Updated',
                'email' => 'user1.updated@example.com',
                'role' => 'familiar',
                'is_approved' => true,
                'location' => null,
                'phone' => null,
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'admin');

        $this->withToken($token)->getJson('/api/admin/dashboard-summary')->assertOk();

        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
    }

    public function test_admin_can_perform_consecutive_modifications_without_losing_session(): void
    {
        $adminPassword = 'admin123';
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
            'password' => Hash::make($adminPassword),
        ]);

        $userOne = User::factory()->create(['name' => 'User One', 'email' => 'user1@example.com']);
        $userTwo = User::factory()->create(['name' => 'User Two', 'email' => 'user2@example.com']);
        $userThree = User::factory()->create(['name' => 'User Three', 'email' => 'user3@example.com']);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => $adminPassword,
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userOne->id}",
            [
                'name' => 'User One Modified',
                'email' => 'user1.modified@example.com',
                'role' => 'familiar',
                'is_approved' => true,
                'location' => 'Location 1',
                'phone' => null,
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userTwo->id}",
            [
                'name' => 'User Two Modified',
                'email' => 'user2.modified@example.com',
                'role' => 'profesional',
                'is_approved' => true,
                'location' => 'Location 2',
                'phone' => null,
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userThree->id}",
            [
                'name' => 'User Three Modified',
                'email' => 'user3.modified@example.com',
                'role' => 'familiar',
                'is_approved' => false,
                'location' => 'Location 3',
                'phone' => null,
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->withToken($token)->getJson("/api/admin/users/{$userOne->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'User One Modified')
            ->assertJsonPath('user.location', 'Location 1');

        $this->withToken($token)->getJson("/api/admin/users/{$userTwo->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'User Two Modified')
            ->assertJsonPath('user.location', 'Location 2');

        $this->withToken($token)->getJson("/api/admin/users/{$userThree->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'User Three Modified')
            ->assertJsonPath('user.location', 'Location 3');
    }

    public function test_admin_routes_remain_available_after_modifications(): void
    {
        $adminPassword = 'admin123';
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
            'password' => Hash::make($adminPassword),
        ]);

        $userToUpdate = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => $adminPassword,
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userToUpdate->id}",
            [
                'name' => 'Updated Test User',
                'email' => 'test.updated@example.com',
                'role' => 'familiar',
                'is_approved' => true,
                'location' => 'New Location',
                'phone' => null,
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->getJson('/api/admin/dashboard-summary')->assertOk();
        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
        $this->withToken($token)->getJson('/api/admin/professional-caregivers')->assertOk();
        $this->withToken($token)->getJson('/api/admin/family-caregivers')->assertOk();
    }
}

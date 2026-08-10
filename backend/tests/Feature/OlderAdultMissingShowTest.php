<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultMissingShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_not_found_for_missing_older_adult(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $this->getJson('/api/admin/older-adults/999')
            ->assertNotFound();
    }
}

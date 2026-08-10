<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultEmptyListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_empty_older_adult_list(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $this->assertDatabaseCount('older_adults', 0);

        $this->getJson('/api/admin/older-adults')
            ->assertOk()
            ->assertJsonCount(0, 'older_adults')
            ->assertExactJson([
                'older_adults' => [],
            ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VacationRequestEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_create_vacation_request(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $startDate = Carbon::today()->addDays(5)->toDateString();
        $endDate = Carbon::today()->addDays(8)->toDateString();

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/vacation-requests', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Viaje familiar programado.',
        ])
            ->assertCreated()
            ->assertJsonPath('vacation_request.user_id', $professional->id)
            ->assertJsonPath('vacation_request.status', 'pending')
            ->assertJsonPath('vacation_request.start_date', $startDate)
            ->assertJsonPath('vacation_request.end_date', $endDate);

        $this->assertDatabaseHas('vacation_requests', [
            'user_id' => $professional->id,
            'status' => 'pending',
            'reason' => 'Viaje familiar programado.',
        ]);
    }

    public function test_pending_professional_cannot_create_vacation_request(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/vacation-requests', [
            'start_date' => Carbon::today()->addDay()->toDateString(),
            'end_date' => Carbon::today()->addDays(3)->toDateString(),
            'reason' => 'Descanso.',
        ])->assertForbidden();
    }

    public function test_vacation_request_requires_end_date_after_start_date(): void
    {
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->postJson('/api/professional/vacation-requests', [
            'start_date' => Carbon::today()->addDays(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'reason' => 'Fechas invalidas.',
        ])->assertUnprocessable();
    }

    public function test_admin_can_list_vacation_requests(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        VacationRequest::create([
            'user_id' => $professional->id,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'reason' => 'Vacaciones.',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/vacation-requests')
            ->assertOk()
            ->assertJsonPath('vacation_requests.0.user_id', $professional->id)
            ->assertJsonPath('vacation_requests.0.user.name', $professional->name);
    }

    public function test_admin_can_approve_vacation_request(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $vacationRequest = VacationRequest::create([
            'user_id' => $professional->id,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'reason' => 'Vacaciones.',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/vacation-requests/{$vacationRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('vacation_request.status', 'approved')
            ->assertJsonPath('vacation_request.reviewer.id', $admin->id);

        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacationRequest->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_review_vacation_request_twice(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $vacationRequest = VacationRequest::create([
            'user_id' => $professional->id,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'reason' => 'Vacaciones.',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => Carbon::now(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/vacation-requests/{$vacationRequest->id}/reject")
            ->assertUnprocessable();
    }
}

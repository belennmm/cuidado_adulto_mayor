<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicationInventoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_older_adult_has_an_independent_medication_inventory(): void
    {
        $admin = $this->actingAsAdmin();
        $firstAdult = $this->createOlderAdult($admin, 'Rosa Martinez');
        $secondAdult = $this->createOlderAdult($admin, 'Carlos Lopez');

        $firstItem = $this->postJson('/api/admin/medications/inventory', [
            ...$this->validInventoryPayload($firstAdult),
            'quantity' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('medication.older_adult_id', $firstAdult->id)
            ->assertJsonPath('medication.older_adult_name', 'Rosa Martinez')
            ->assertJsonPath('medication.quantity', 12)
            ->json('medication');

        $secondItem = $this->postJson('/api/admin/medications/inventory', [
            ...$this->validInventoryPayload($secondAdult),
            'quantity' => 35,
        ])
            ->assertCreated()
            ->assertJsonPath('medication.older_adult_id', $secondAdult->id)
            ->assertJsonPath('medication.older_adult_name', 'Carlos Lopez')
            ->assertJsonPath('medication.quantity', 35)
            ->json('medication');

        $this->assertNotSame($firstItem['id'], $secondItem['id']);
        $this->assertSame($firstItem['medication_id'], $secondItem['medication_id']);
        $this->assertDatabaseCount('medications', 1);
        $this->assertDatabaseCount('older_adult_medications', 2);

        $this->getJson('/api/admin/medications/inventory')
            ->assertOk()
            ->assertJsonCount(2, 'inventory');

        $this->getJson("/api/admin/medications/inventory?older_adult_id={$firstAdult->id}")
            ->assertOk()
            ->assertJsonCount(1, 'inventory')
            ->assertJsonPath('inventory.0.id', $firstItem['id'])
            ->assertJsonPath('inventory.0.quantity', 12);
    }

    public function test_updating_one_adults_inventory_does_not_change_another_adults_stock(): void
    {
        $admin = $this->actingAsAdmin();
        $firstAdult = $this->createOlderAdult($admin, 'Rosa Martinez');
        $secondAdult = $this->createOlderAdult($admin, 'Carlos Lopez');
        $medication = Medication::create(['name' => 'Losartan', 'is_active' => true]);
        $firstItem = $this->createInventoryItem($firstAdult, $medication, 12);
        $secondItem = $this->createInventoryItem($secondAdult, $medication, 35);

        $this->putJson("/api/admin/medications/inventory/{$firstItem->id}", [
            ...$this->validInventoryPayload($firstAdult),
            'quantity' => 20,
            'minimum_stock' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('medication.id', $firstItem->id)
            ->assertJsonPath('medication.quantity', 20);

        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $firstItem->id,
            'older_adult_id' => $firstAdult->id,
            'quantity' => 20,
            'minimum_stock' => 8,
        ]);
        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $secondItem->id,
            'older_adult_id' => $secondAdult->id,
            'quantity' => 35,
            'minimum_stock' => 10,
        ]);
    }

    public function test_stock_adjustments_only_affect_the_selected_adults_inventory(): void
    {
        $admin = $this->actingAsAdmin();
        $firstAdult = $this->createOlderAdult($admin, 'Rosa Martinez');
        $secondAdult = $this->createOlderAdult($admin, 'Carlos Lopez');
        $medication = Medication::create(['name' => 'Losartan', 'is_active' => true]);
        $firstItem = $this->createInventoryItem($firstAdult, $medication, 12);
        $secondItem = $this->createInventoryItem($secondAdult, $medication, 35);

        $this->patchJson("/api/admin/medications/inventory/{$firstItem->id}/stock", [
            'action' => 'decrease',
            'amount' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('medication.quantity', 7)
            ->assertJsonPath('medication.older_adult_id', $firstAdult->id);

        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $firstItem->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $secondItem->id,
            'quantity' => 35,
        ]);
    }

    public function test_inventory_requires_an_existing_older_adult_and_valid_stock_data(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/medications/inventory', [
            'older_adult_id' => 999999,
            'name' => '',
            'presentation' => '',
            'quantity' => -1,
            'unit' => '',
            'minimum_stock' => -5,
            'expiration_date' => 'fecha-invalida',
            'is_active' => 'activo',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'older_adult_id',
                'name',
                'presentation',
                'quantity',
                'unit',
                'minimum_stock',
                'expiration_date',
                'is_active',
            ]);

        $this->assertDatabaseCount('medications', 0);
        $this->assertDatabaseCount('older_adult_medications', 0);
    }

    public function test_deleting_inventory_for_one_adult_keeps_the_other_adults_inventory(): void
    {
        $admin = $this->actingAsAdmin();
        $firstAdult = $this->createOlderAdult($admin, 'Rosa Martinez');
        $secondAdult = $this->createOlderAdult($admin, 'Carlos Lopez');
        $medication = Medication::create(['name' => 'Losartan', 'is_active' => true]);
        $firstItem = $this->createInventoryItem($firstAdult, $medication, 12);
        $secondItem = $this->createInventoryItem($secondAdult, $medication, 35);

        $this->deleteJson("/api/admin/medications/inventory/{$firstItem->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Medicamento eliminado del inventario del adulto mayor.');

        $this->assertDatabaseMissing('older_adult_medications', ['id' => $firstItem->id]);
        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $secondItem->id,
            'quantity' => 35,
        ]);
        $this->assertDatabaseHas('medications', ['id' => $medication->id]);
    }

    public function test_non_admin_users_cannot_manage_individual_inventories(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]));

        $this->getJson('/api/admin/medications/inventory')
            ->assertForbidden();
        $this->postJson('/api/admin/medications/inventory', [])
            ->assertForbidden();
    }

    public function test_editing_an_older_adults_clinical_profile_preserves_its_inventory(): void
    {
        $admin = $this->actingAsAdmin();
        $olderAdult = $this->createOlderAdult($admin, 'Rosa Martinez');
        $medication = Medication::create(['name' => 'Losartan', 'is_active' => true]);
        $inventoryItem = $this->createInventoryItem($olderAdult, $medication, 24);

        $this->putJson("/api/admin/older-adults/{$olderAdult->id}", [
            'full_name' => 'Rosa Martinez',
            'status' => 'Estable',
            'medications' => [[
                'id' => $inventoryItem->id,
                'name' => 'Losartan',
                'dosage' => '1 tableta',
                'schedule' => '08:00',
                'days' => ['lunes'],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $inventoryItem->id,
            'older_adult_id' => $olderAdult->id,
            'quantity' => 24,
            'minimum_stock' => 10,
            'expiration_date' => '2030-10-15 00:00:00',
            'dosage' => '1 tableta',
            'schedule' => '08:00',
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    private function createOlderAdult(User $admin, string $name): OlderAdult
    {
        return OlderAdult::create([
            'full_name' => $name,
            'status' => 'Estable',
            'created_by' => $admin->id,
        ]);
    }

    private function createInventoryItem(OlderAdult $olderAdult, Medication $medication, int $quantity): OlderAdultMedication
    {
        return OlderAdultMedication::create([
            'older_adult_id' => $olderAdult->id,
            'medication_id' => $medication->id,
            'presentation' => 'Tableta 50mg',
            'quantity' => $quantity,
            'unit' => 'tabletas',
            'minimum_stock' => 10,
            'expiration_date' => '2030-10-15',
            'is_active' => true,
        ]);
    }

    private function validInventoryPayload(OlderAdult $olderAdult): array
    {
        return [
            'older_adult_id' => $olderAdult->id,
            'name' => 'Losartan',
            'presentation' => 'Tableta 50mg',
            'quantity' => 40,
            'unit' => 'tabletas',
            'minimum_stock' => 10,
            'expiration_date' => '2030-10-15',
            'is_active' => true,
        ];
    }
}

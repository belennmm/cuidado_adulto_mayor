<?php

namespace Tests\Unit;

use App\Models\OlderAdultMedication;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicationInventoryStatusTest extends TestCase
{
    public static function inventoryCases(): array
    {
        return [
            'expired' => [-1, 20, 5, 'expired'],
            'expiring soon' => [15, 20, 5, 'expiring_soon'],
            'low stock' => [60, 5, 5, 'low_stock'],
            'available' => [60, 20, 5, 'available'],
        ];
    }

    #[DataProvider('inventoryCases')]
    public function test_inventory_status_follows_expiration_and_stock_rules(
        int $expirationOffset,
        int $quantity,
        int $minimumStock,
        string $expectedKey
    ): void {
        $today = Carbon::parse('2026-07-17');
        $inventoryItem = new OlderAdultMedication([
            'expiration_date' => $today->copy()->addDays($expirationOffset),
            'quantity' => $quantity,
            'minimum_stock' => $minimumStock,
        ]);

        $this->assertSame($expectedKey, $inventoryItem->inventoryStatus($today)['key']);
    }
}

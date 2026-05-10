<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Medication extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'presentation',
        'quantity',
        'unit',
        'minimum_stock',
        'expiration_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'minimum_stock' => 'integer',
            'expiration_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function olderAdultMedications(): HasMany
    {
        return $this->hasMany(OlderAdultMedication::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function inventoryStatus(?Carbon $today = null): array
    {
        $referenceDate = ($today ?: Carbon::now(config('app.timezone')))->copy()->startOfDay();
        $expirationDate = $this->expiration_date?->copy()->startOfDay();

        if ($expirationDate && $expirationDate->lt($referenceDate)) {
            return [
                'key' => 'expired',
                'label' => 'Vencido',
            ];
        }

        if ($expirationDate && $expirationDate->lte($referenceDate->copy()->addDays(30))) {
            return [
                'key' => 'expiring_soon',
                'label' => 'Proximo a vencer',
            ];
        }

        if ((int) $this->quantity <= (int) $this->minimum_stock) {
            return [
                'key' => 'low_stock',
                'label' => 'Bajo stock',
            ];
        }

        return [
            'key' => 'available',
            'label' => 'Disponible',
        ];
    }
}

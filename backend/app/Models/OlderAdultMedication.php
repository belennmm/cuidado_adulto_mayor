<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class OlderAdultMedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'older_adult_id',
        'medication_id',
        'presentation',
        'quantity',
        'unit',
        'minimum_stock',
        'expiration_date',
        'dosage',
        'schedule',
        'days',
        'notes',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'quantity' => 'integer',
            'minimum_stock' => 'integer',
            'expiration_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function olderAdult(): BelongsTo
    {
        return $this->belongsTo(OlderAdult::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class, 'older_adult_medication_id');
    }

    public function inventoryStatus(?Carbon $today = null): array
    {
        $referenceDate = ($today ?: Carbon::now(config('app.timezone')))->copy()->startOfDay();
        $expirationDate = $this->expiration_date?->copy()->startOfDay();

        if ($expirationDate && $expirationDate->lt($referenceDate)) {
            return ['key' => 'expired', 'label' => 'Vencido'];
        }

        if ($expirationDate && $expirationDate->lte($referenceDate->copy()->addDays(30))) {
            return ['key' => 'expiring_soon', 'label' => 'Próximo a vencer'];
        }

        if ((int) $this->quantity <= (int) $this->minimum_stock) {
            return ['key' => 'low_stock', 'label' => 'Bajo stock'];
        }

        return ['key' => 'available', 'label' => 'Disponible'];
    }
}

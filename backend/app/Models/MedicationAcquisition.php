<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MedicationAcquisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'medication_id',
        'older_adult_id',
        'quantity',
        'acquired_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'acquired_at' => 'datetime',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function olderAdult(): BelongsTo
    {
        return $this->belongsTo(OlderAdult::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    use HasFactory;

    protected $fillable = [
        'older_adult_id',
        'older_adult_medication_id',
        'medication_id',
        'administration_type',
        'dosage',
        'administration_date',
        'administration_time',
        'notes',
        'recorded_by',
    ];

    public function olderAdult(): BelongsTo
    {
        return $this->belongsTo(OlderAdult::class);
    }

    public function olderAdultMedication(): BelongsTo
    {
        return $this->belongsTo(OlderAdultMedication::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

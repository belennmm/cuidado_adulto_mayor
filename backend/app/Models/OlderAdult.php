<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OlderAdult extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'age',
        'birthdate',
        'gender',
        'room',
        'status',
        'caregiver_family',
        'professional_caregiver_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'allergies',
        'medical_history',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'age' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function professionalCaregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_caregiver_id');
    }
}

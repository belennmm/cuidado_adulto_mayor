<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'family_caregiver_id',
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

    public function familyCaregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_caregiver_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(OlderAdultMedication::class)
            ->where('is_active', true)
            ->orderBy('created_at');
    }

    public function medicationAssignments(): HasMany
    {
        return $this->hasMany(OlderAdultMedication::class)->orderBy('created_at');
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class)->orderByDesc('administration_date');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class)->orderByDesc('incident_date');
    }

    public function routineNotes(): HasMany
    {
        return $this->hasMany(RoutineNote::class)->orderByDesc('note_date')->orderByDesc('updated_at');
    }

    public function rutinas(): HasMany
    {
        return $this->hasMany(Rutina::class)->orderBy('horario')->orderBy('nombre');
    }
}

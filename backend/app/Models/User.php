<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Security attributes are written explicitly by controlled services, never by fill/update payloads.
    protected $fillable = [
        'name',
        'email',
        'location',
        'phone',
        'birthdate',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_approved' => 'boolean',
            'birthdate' => 'date',
            'privacy_consent_at' => 'datetime',
        ];
    }

    public function roleEnum(): ?UserRole
    {
        return UserRole::fromValue($this->role);
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->roleEnum() === $role;
    }

    public function routineNotes(): HasMany
    {
        return $this->hasMany(RoutineNote::class, 'professional_caregiver_id')
            ->orderByDesc('note_date')
            ->orderByDesc('updated_at');
    }

    public function rutinas(): HasMany
    {
        return $this->hasMany(Rutina::class, 'created_by')->orderByDesc('created_at');
    }

    public function mobilityExercises(): HasMany
    {
        return $this->hasMany(MobilityExercise::class, 'created_by')->orderBy('sort_order');
    }
}

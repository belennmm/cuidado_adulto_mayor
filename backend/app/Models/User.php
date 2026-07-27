<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_approved',
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
        ];
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

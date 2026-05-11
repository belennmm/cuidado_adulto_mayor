<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rutina extends Model
{
    use HasFactory;

    protected $table = 'rutinas';

    protected $fillable = [
        'older_adult_id',
        'created_by',
        'nombre',
        'horario',
        'actividades',
        'actividades_completadas',
    ];

    protected function casts(): array
    {
        return [
            'actividades' => 'array',
            'actividades_completadas' => 'array',
        ];
    }

    public function olderAdult(): BelongsTo
    {
        return $this->belongsTo(OlderAdult::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

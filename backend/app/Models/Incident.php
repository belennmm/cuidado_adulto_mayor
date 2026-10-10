<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    // Intermediate states remain optional; a resolved/closed incident cannot move backwards.
    public const STATUS_TRANSITIONS = [
        'abierto' => ['abierto', 'en_progreso', 'resuelto', 'cerrado'],
        'en_progreso' => ['en_progreso', 'resuelto', 'cerrado'],
        'resuelto' => ['resuelto', 'cerrado'],
        'cerrado' => ['cerrado'],
    ];

    // Existing seed/legacy records; public writes still use the canonical states.
    public const LEGACY_STATUS_ALIASES = ['pendiente' => 'abierto', 'en seguimiento' => 'en_progreso'];

    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'adult_name',
        'older_adult_id',
        'severity',
        'status',
        'incident_date',
        'incident_time',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function olderAdult(): BelongsTo
    {
        return $this->belongsTo(OlderAdult::class);
    }
}

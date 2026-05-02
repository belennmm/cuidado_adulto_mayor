<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
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

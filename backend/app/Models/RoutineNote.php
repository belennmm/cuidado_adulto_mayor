<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'older_adult_id',
        'professional_caregiver_id',
        'content',
        'note_date',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
        ];
    }

    public function olderAdult(): BelongsTo
    {
        return $this->belongsTo(OlderAdult::class);
    }

    public function professionalCaregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_caregiver_id');
    }
}

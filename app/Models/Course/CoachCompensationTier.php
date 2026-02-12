<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;

class CoachCompensationTier extends Model
{
    protected $fillable = [
        'coach_id',
        'min_participants',
        'max_participants',
        'compensation',
        'sort_order'
    ];

    protected $casts = [
        'compensation' => 'decimal:2',
        'min_participants' => 'integer',
        'max_participants' => 'integer',
        'sort_order' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if participant count falls within this tier
     */
    public function matches(int $participantCount): bool
    {
        return $participantCount >= $this->min_participants 
            && $participantCount <= $this->max_participants;
    }

    /**
     * Format the tier range for display
     */
    public function getRangeTextAttribute(): string
    {
        return "{$this->min_participants}-{$this->max_participants} Teilnehmer";
    }
}

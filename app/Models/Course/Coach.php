<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optional: Coach ↔ Kurse
    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    // Abrechnungsstufen
    public function compensationTiers()
    {
        return $this->hasMany(CoachCompensationTier::class)->orderBy('sort_order')->orderBy('min_participants');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate compensation based on participant count
     */
    public function calculateCompensation(int $participantCount): ?float
    {
        $tier = $this->compensationTiers()
            ->where('min_participants', '<=', $participantCount)
            ->where('max_participants', '>=', $participantCount)
            ->first();

        return $tier?->compensation;
    }
}
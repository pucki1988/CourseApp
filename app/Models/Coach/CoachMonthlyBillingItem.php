<?php

namespace App\Models\Coach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachMonthlyBillingItem extends Model
{
    protected $fillable = [
        'coach_monthly_billing_id',
        'course_slot_id',
        'date',
        'course_title',
        'start_time',
        'end_time',
        'participant_count',
        'compensation',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'compensation' => 'decimal:2',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(CoachMonthlyBilling::class, 'coach_monthly_billing_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(CourseSlot::class, 'course_slot_id');
    }
}

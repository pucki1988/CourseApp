<?php

namespace App\Models\Coach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachMonthlyBilling extends Model
{
    protected $fillable = [
        'coach_id',
        'year',
        'month',
        'period_start',
        'period_end',
        'total_slots',
        'total_compensation',
        'status',
        'mail_recipient',
        'mail_sent_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'mail_sent_at' => 'datetime',
        'total_compensation' => 'decimal:2',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CoachMonthlyBillingItem::class);
    }

    public function periodLabel(): string
    {
        return now()
            ->setYear($this->year)
            ->setMonth($this->month)
            ->setDay(1)
            ->locale('de')
            ->translatedFormat('F Y');
    }
}

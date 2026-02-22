<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $fillable = [
        'membership_type_id',
        'started_at',
        'ended_at',
        'status',
        'payer_member_id',
        'calculated_amount',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
        'calculated_amount' => 'decimal:2',
    ];

    public function type()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'membership_member')
            ->withPivot('role', 'amount_override', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function payer()
    {
        return $this->belongsTo(Member::class, 'payer_member_id');
    }

    public function payments()
    {
        return $this->hasMany(MembershipPayment::class);
    }

    /**
     * Get billing interval from type
     */
    public function getBillingIntervalAttribute(): string
    {
        return $this->type->billing_interval ?? 'monthly';
    }

    /**
     * Get billing mode from type
     */
    public function getBillingModeAttribute(): string
    {
        return $this->type->billing_mode ?? 'recurring';
    }

    /**
     * Get human-readable billing interval label
     */
    public function getBillingIntervalLabelAttribute(): string
    {
        return $this->type->billing_interval_label ?? 'Unbekannt';
    }
}

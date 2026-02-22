<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;

class MembershipType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'base_amount',
        'billing_mode',
        'billing_interval',
        'conditions',
        'active',
        'is_club_membership',
        'sort_order',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'conditions' => 'array',
        'active' => 'boolean',
        'is_club_membership' => 'boolean',
    ];

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get human-readable billing interval label
     */
    public function getBillingIntervalLabelAttribute(): string
    {
        return match($this->billing_interval) {
            'monthly' => 'Monatlich',
            'quarterly' => 'Vierteljährlich',
            'semi_annual' => 'Halbjährlich',
            'annual' => 'Jährlich',
            default => 'Unbekannt',
        };
    }

    /**
     * Calculate total price for billing interval (base_amount ist monatlich)
     */
    public function getTotalPriceAttribute(): float
    {
        return match($this->billing_interval) {
            'monthly' => (float) $this->base_amount,
            'quarterly' => (float) $this->base_amount * 3,
            'semi_annual' => (float) $this->base_amount * 6,
            'annual' => (float) $this->base_amount * 12,
            default => (float) $this->base_amount,
        };
    }

    /**
     * Get multiplier for billing interval
     */
    public function getIntervalMultiplierAttribute(): int
    {
        return match($this->billing_interval) {
            'monthly' => 1,
            'quarterly' => 3,
            'semi_annual' => 6,
            'annual' => 12,
            default => 1,
        };
    }
}

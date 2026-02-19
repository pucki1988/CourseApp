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
        'interval',
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
}

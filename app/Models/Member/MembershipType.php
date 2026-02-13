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
        'sort_order',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'conditions' => 'array',
        'active' => 'boolean',
    ];

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }
}

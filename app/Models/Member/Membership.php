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
        'billing_cycle',
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
}

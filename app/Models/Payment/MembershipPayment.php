<?php

namespace App\Models\Payment;

use App\Models\Member\Membership;
use Illuminate\Database\Eloquent\Model;

class MembershipPayment extends Model
{
    protected $fillable = [
        'membership_id',
        'period_start',
        'period_end',
        'due_date',
        'amount',
        'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'source');
    }
}

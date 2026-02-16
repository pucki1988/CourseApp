<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'member_id',
        'iban',
        'bic',
        'account_holder',
        'mandate_reference',
        'mandate_signed_at',
        'is_default',
        'status',
    ];

    protected $casts = [
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'account_holder' => 'encrypted',
        'mandate_signed_at' => 'date',
        'is_default' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function payments()
    {
        return $this->hasMany(MembershipPayment::class);
    }
}

<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPointTransaction extends Model
{
    protected $fillable = [
        'loyalty_account_id',
        'points',
        'type',
        'origin',
        'source_type',
        'source_id',
        'description',
        'balance_after',
    ];

    public function account()
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }
}

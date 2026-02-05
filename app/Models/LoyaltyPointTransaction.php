<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPointTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'points',
        'type',
        'source_type',
        'source_id',
        'description',
        'balance_after',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

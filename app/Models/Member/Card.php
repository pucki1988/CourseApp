<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Loyalty\LoyaltyAccount;

class Card extends Model
{
    protected $fillable = ['uuid', 'member_id','active', 'revoked_at', 'loyalty_account_id'];

    protected $casts = [
        'active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function loyaltyAccount()
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }


    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function revoke(): void
    {
        $this->update([
            'active' => false,
            'revoked_at' => now(),
        ]);
    }

}

<?php

namespace App\Models\Member;

use App\Models\CheckinToken;
use App\Models\Loyalty\LoyaltyAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Card extends Model
{
    protected $fillable = ['member_id', 'active', 'revoked_at', 'loyalty_account_id'];

    protected $casts = [
        'active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $card) {
            $card->issueCheckinToken();
        });

        static::deleting(function (self $card) {
            $card->checkinToken()->delete();
        });
    }

    public function loyaltyAccount()
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }


    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function checkinToken(): MorphOne
    {
        return $this->morphOne(CheckinToken::class, 'tokenable');
    }

    public function issueCheckinToken(): CheckinToken
    {
        $existingToken = $this->checkinToken()->first();

        if ($existingToken) {
            return $existingToken;
        }

        return $this->checkinToken()->create();
    }

    public function checkinIdentifier(): ?string
    {
        return $this->checkinToken?->token;
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

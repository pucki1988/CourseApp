<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Member\Card;

class LoyaltyAccount extends Model
{
    protected $fillable = ['type'];


    public function transactions()
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }



    public function balance()
    {
        return $this->transactions()->sum('points');
    }


    public function balanceByOrigin(string $origin)
    {
        return $this->transactions()
        ->where('origin', $origin)
        ->sum('points');
    }
}
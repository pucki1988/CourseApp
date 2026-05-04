<?php

namespace App\Models\Accounting;

use App\Models\Member\Card;
use App\Models\Member\Member;
use App\Models\User;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\HasWallet;
use BeyondCode\Vouchers\Models\Voucher;
use BeyondCode\Vouchers\Traits\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model implements Wallet
{
    use HasFactory;
    use HasWallet;
    use HasVouchers;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function redeemedVouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, config('vouchers.relation_table', 'account_voucher'))
            ->withPivot('redeemed_at');
    }
}
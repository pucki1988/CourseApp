<?php

namespace App\Models\Accounting;

use App\Models\Member\Card;
use App\Models\Member\Member;
use App\Models\Shop\Order;
use App\Models\User;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Wallet as WalletModel;
use Bavix\Wallet\Traits\HasWallet;
use Bavix\Wallet\Traits\HasWallets;
use BeyondCode\Vouchers\Models\Voucher;
use BeyondCode\Vouchers\Traits\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model implements Wallet
{
    public const WALLET_SPORTS_VOUCHER = 'sports-voucher';
    public const WALLET_LOYALTY_POINTS = 'loyalty-points';

    use HasFactory;
    use HasWallet;
    use HasWallets;
    use HasVouchers;

    protected static function booted(): void
    {
        static::created(function (self $account): void {
            $account->ensureSystemWallets();
        });
    }

    /**
     * @return array<string, string>
     */
    public static function systemWalletDefinitions(): array
    {
        return [
            self::WALLET_SPORTS_VOUCHER => 'Sports Voucher',
            self::WALLET_LOYALTY_POINTS => 'Loyalty Points',
        ];
    }

    public function ensureSystemWallets(): void
    {
        foreach (self::systemWalletDefinitions() as $slug => $name) {
            if (! $this->hasWallet($slug)) {
                $this->createWallet([
                    'name' => $name,
                    'slug' => $slug,
                ]);
            }
        }
    }

    public function walletBySlug(string $slug): WalletModel
    {
        return $this->getWallet($slug) ?? $this->createWallet([
            'name' => self::systemWalletDefinitions()[$slug] ?? ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
        ]);
    }

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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function redeemedVouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, config('vouchers.relation_table', 'account_voucher'))
            ->withPivot('redeemed_at')
            ->withTimestamps();
    }
}
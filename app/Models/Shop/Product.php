<?php

namespace App\Models\Shop;

use App\Models\Shop\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const TYPE_VOUCHER_WALLET_TOPUP = 'voucher_wallet_topup';

    protected $fillable = [
        'type',
        'sku',
        'name',
        'description',
        'price',
        'currency',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isVoucherWalletTopup(): bool
    {
        return $this->type === self::TYPE_VOUCHER_WALLET_TOPUP;
    }
}
<?php

namespace App\Models\Shop;

use App\Models\Accounting\Account;
use App\Models\Payment\Payment;
use App\Models\Shop\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'status',
        'customer_name',
        'customer_email',
        'currency',
        'subtotal_amount',
        'total_amount',
        'paid_at',
        'canceled_at',
        'meta',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'canceled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'source');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingPayment(): bool
    {
        return $this->status === 'pending_payment';
    }
}
<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'amount',
        'currency',
        'status',
        'provider_refund_id',
        'completed_at',
        'failed_at',
        'canceled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }
}

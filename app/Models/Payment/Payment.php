<?php

namespace App\Models\Payment;

use App\Models\Member\BankAccount;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'amount',
        'currency',
        'method',
        'provider',
        'provider_payment_id',
        'checkout_url',
        'status',
        'paid_at',
        'failed_at',
        'canceled_at',
        'refunded_at',
        'source_type',
        'source_id',
        'payment_run_id',
        'bank_account_id',
        'reference',
        'meta',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'failed_at'   => 'datetime',
        'canceled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function source()
    {
        return $this->morphTo();
    }

    public function paymentRun()
    {
        return $this->belongsTo(PaymentRun::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    // ---- Status-Helpers --------------------------------------------

    public function isPaid(): bool     { return $this->status === 'paid'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }
    public function isCanceled(): bool { return $this->status === 'canceled'; }
    public function isOpen(): bool     { return $this->status === 'open'; }
}
<?php

namespace App\Models\Payment;

use App\Models\Member\BankAccount;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'amount',
        'method',
        'status',
        'paid_at',
        'source_type',
        'source_id',
        'payment_run_id',
        'bank_account_id',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
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
}
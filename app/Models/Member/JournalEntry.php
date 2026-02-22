<?php

namespace App\Models\Member;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_date',
        'entry_type',
        'reference',
        'description',
        'amount',
        'debit_account',
        'credit_account',
        'bank_reference',
        'cost_center',
        'line_items',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'line_items' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create a journal entry for a payment run
     */
    public static function createForPaymentRun(PaymentRun $paymentRun, ?string $bankReference = null): self
    {
        return self::create([
            'entry_date' => $paymentRun->execution_date,
            'entry_type' => 'payment_run',
            'reference' => $paymentRun->reference,
            'description' => "SEPA Einzug {$paymentRun->reference}",
            'amount' => $paymentRun->total_amount,
            'debit_account' => '1200', // Bank (später konfigurierbar)
            'credit_account' => '4000', // Mitgliedsbeiträge (später konfigurierbar)
            'bank_reference' => $bankReference ?? 'Hauptkonto',
            'created_by' => auth()->id(),
        ]);
    }
}

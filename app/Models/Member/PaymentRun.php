<?php

namespace App\Models\Member;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentRun extends Model
{
    protected $fillable = [
        'reference',
        'execution_date',
        'status',
        'total_amount',
        'payment_count',
        'notes',
        'created_by',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'execution_date' => 'date',
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class);
    }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reference', 'reference')
            ->where('entry_type', 'payment_run');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate and update totals from associated payments
     */
    public function recalculateTotals(): void
    {
        $this->update([
            'total_amount' => $this->payments()->sum('amount'),
            'payment_count' => $this->payments()->count(),
        ]);
    }

    /**
     * Check if run can be edited
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft']);
    }

    /**
     * Check if run can be submitted
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft' && $this->payments()->count() > 0;
    }
}

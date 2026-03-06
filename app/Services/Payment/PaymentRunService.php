<?php

namespace App\Services\Payment;

use App\Models\Payment\JournalEntry;
use App\Models\Payment\MembershipPayment;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentRunService
{
    /**
     * Create a new payment run with pending payments
     */
    public function createPaymentRun(Carbon $executionDate, ?string $notes = null): PaymentRun
    {
        $reference = $this->generateReference($executionDate);

        $paymentRun = PaymentRun::create([
            'reference' => $reference,
            'execution_date' => $executionDate,
            'status' => 'draft',
            'notes' => $notes,
            'created_by' => Auth::id(),
        ]);

        return $paymentRun;
    }

    /**
     * Add pending payments to a payment run
     */
    public function addPendingPayments(PaymentRun $paymentRun, ?Carbon $upToDate = null): int
    {
        if (!$paymentRun->isEditable()) {
            throw new \InvalidArgumentException('Payment run cannot be edited in current status');
        }

        $query = MembershipPayment::where('status', 'pending')
            ->whereDoesntHave('payments', function ($paymentQuery) {
                $paymentQuery
                    ->where('status', 'pending')
                    ->whereHas('paymentRun', function ($runQuery) {
                        $runQuery->whereIn('status', ['draft', 'submitted']);
                    });
            })
            ->with(['membership.payer.bankAccounts']);

        if ($upToDate) {
            $query->where('due_date', '<=', $upToDate);
        }

        $count = 0;

        $query->chunkById(200, function ($membershipPayments) use ($paymentRun, &$count) {
            foreach ($membershipPayments as $membershipPayment) {
                $defaultBankAccountId = $membershipPayment->membership?->payer?->bankAccounts
                    ?->firstWhere('is_default', true)?->id;

                Payment::create([
                    'amount' => $membershipPayment->amount,
                    'method' => 'sepa',
                    'status' => 'pending',
                    'paid_at' => null,
                    'source_type' => MembershipPayment::class,
                    'source_id' => $membershipPayment->id,
                    'payment_run_id' => $paymentRun->id,
                    'bank_account_id' => $defaultBankAccountId,
                    'reference' => 'run-'.$paymentRun->id.'-mp-'.$membershipPayment->id,
                ]);
                $count++;
            }
        });

        $paymentRun->recalculateTotals();

        return $count;
    }

    /**
     * Remove payments from run
     */
    public function removePayments(PaymentRun $paymentRun, array $paymentIds): int
    {
        if (!$paymentRun->isEditable()) {
            throw new \InvalidArgumentException('Payment run cannot be edited in current status');
        }

        $count = Payment::whereIn('id', $paymentIds)
            ->where('payment_run_id', $paymentRun->id)
            ->update(['payment_run_id' => null]);

        $paymentRun->recalculateTotals();

        return $count;
    }

    /**
     * Submit payment run (marks it as ready for processing)
     */
    public function submitPaymentRun(PaymentRun $paymentRun, ?string $bankReference = null): PaymentRun
    {
        if (!$paymentRun->canBeSubmitted()) {
            throw new \InvalidArgumentException('Payment run cannot be submitted');
        }

        $paymentRun->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Create journal entry
        JournalEntry::createForPaymentRun($paymentRun, $bankReference);

        return $paymentRun;
    }

    /**
     * Mark payment run as completed
     */
    public function completePaymentRun(PaymentRun $paymentRun): PaymentRun
    {
        if ($paymentRun->status !== 'submitted') {
            throw new \InvalidArgumentException('Only submitted payment runs can be completed');
        }

        // Mark all payments as paid
        $paymentRun->payments()->update([
            'status' => 'collected',
            'paid_at' => now(),
        ]);

        $paymentRun->payments()
            ->where('source_type', MembershipPayment::class)
            ->get()
            ->each(function (Payment $payment) {
                $payment->source?->update([
                    'status' => 'collected',
                ]);
            });

        $paymentRun->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $paymentRun;
    }

    /**
     * Cancel payment run
     */
    public function cancelPaymentRun(PaymentRun $paymentRun): PaymentRun
    {
        if ($paymentRun->status === 'completed') {
            throw new \InvalidArgumentException('Completed payment runs cannot be cancelled');
        }

        if ($paymentRun->status === 'cancelled') {
            return $paymentRun;
        }

        DB::transaction(function () use ($paymentRun) {
            $paymentRun->payments()
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'paid_at' => null,
                ]);

            $paymentRun->update([
                'status' => 'cancelled',
            ]);
        });

        $paymentRun->recalculateTotals();

        return $paymentRun;
    }

    /**
     * Mark individual payments as paid/failed within a run
     */
    public function updatePaymentStatus(Payment $payment, string $status): Payment
    {
        $validStatuses = ['collected', 'failed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $payment->update([
            'status' => $status,
            'paid_at' => $status === 'collected' ? now() : null,
        ]);

        if ($payment->source instanceof MembershipPayment) {
            $payment->source->update([
                'status' => $status,
            ]);
        }

        // Recalculate run totals if needed
        if ($payment->paymentRun) {
            $payment->paymentRun->recalculateTotals();
        }

        return $payment;
    }

    /**
     * Generate unique reference for payment run
     */
    protected function generateReference(Carbon $date): string
    {
        return sprintf('SEPA-%s-%02d', $date->format('Y-m'), PaymentRun::whereYear('execution_date', $date->year)->whereMonth('execution_date', $date->month)->count() + 1);
    }

    /**
     * Get payments for a run grouped by status
     */
    public function getPaymentsByStatus(PaymentRun $paymentRun): Collection
    {
        return $paymentRun->payments()
            ->with(['source.membership.type', 'source.membership.payer', 'bankAccount'])
            ->get()
            ->groupBy('status');
    }

    /**
     * Get all pending payments that can be added to a run
     */
    public function getPendingPayments(?Carbon $upToDate = null): Collection
    {
        $query = MembershipPayment::where('status', 'pending')
            ->whereDoesntHave('payments', function ($paymentQuery) {
                $paymentQuery->where('status', 'pending')
                    ->whereNotNull('payment_run_id');
            })
            ->with(['membership.type', 'membership.payer']);

        if ($upToDate) {
            $query->where('due_date', '<=', $upToDate);
        }

        return $query->orderBy('due_date')->get();
    }

    /**
     * Get summary statistics for pending payments
     */
    public function getPendingPaymentsSummary(?Carbon $upToDate = null): array
    {
        $payments = $this->getPendingPayments($upToDate);

        return [
            'count' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'oldest_date' => $payments->min('due_date'),
            'newest_date' => $payments->max('due_date'),
        ];
    }
}

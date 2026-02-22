<?php

namespace App\Services\Member;

use App\Models\Member\JournalEntry;
use App\Models\Member\MembershipPayment;
use App\Models\Member\PaymentRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            'created_by' => auth()->id(),
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
            ->whereNull('payment_run_id')
            ->whereNotNull('bank_account_id'); // Nur Zahlungen mit Bankverbindung

        if ($upToDate) {
            $query->where('due_date', '<=', $upToDate);
        }

        $count = $query->update([
            'payment_run_id' => $paymentRun->id,
        ]);

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

        $count = MembershipPayment::whereIn('id', $paymentIds)
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
            'status' => 'paid',
            'paid_at' => now(),
        ]);

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

        // Remove payment run reference from payments
        $paymentRun->payments()->update(['payment_run_id' => null]);

        $paymentRun->update([
            'status' => 'cancelled',
        ]);

        return $paymentRun;
    }

    /**
     * Mark individual payments as paid/failed within a run
     */
    public function updatePaymentStatus(MembershipPayment $payment, string $status): MembershipPayment
    {
        $validStatuses = ['paid', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $payment->update([
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

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
            ->with(['membership.type', 'membership.payer', 'bankAccount'])
            ->get()
            ->groupBy('status');
    }

    /**
     * Get all pending payments that can be added to a run
     */
    public function getPendingPayments(?Carbon $upToDate = null): Collection
    {
        $query = MembershipPayment::where('status', 'pending')
            ->whereNull('payment_run_id')
            ->whereNotNull('bank_account_id')
            ->with(['membership.type', 'membership.payer', 'bankAccount']);

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

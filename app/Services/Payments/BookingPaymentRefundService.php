<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentFailedException;
use App\Models\Course\CourseBooking;

class BookingPaymentRefundService
{
    public function __construct(
        protected PaymentServiceResolver $paymentServiceResolver,
    ) {}

    public function refund(CourseBooking $booking, float $amount): void
    {
        $payments = $booking->payments()
            ->where('status', 'paid')
            ->orderBy('id')
            ->get();

        if ($payments->isEmpty() && $booking->payment_transaction_id) {
            $legacyPayment = $booking->payments()
                ->where('provider_payment_id', $booking->payment_transaction_id)
                ->first();

            $payments->push($legacyPayment ?? $booking->payments()->create([
                'amount' => $booking->total_price,
                'currency' => 'EUR',
                'method' => 'pending',
                'provider' => 'mollie',
                'provider_payment_id' => $booking->payment_transaction_id,
                'status' => 'paid',
                'paid_at' => $booking->updated_at,
            ]));
        }

        $remainingCents = (int) round($amount * 100);
        foreach ($payments as $payment) {
            $refundedCents = (int) round(
                (float) $payment->refunds()
                    ->whereNotIn('status', ['failed', 'canceled'])
                    ->sum('amount') * 100
            );
            $availableCents = max(0, (int) round((float) $payment->amount * 100) - $refundedCents);
            $refundCents = min($remainingCents, $availableCents);

            if ($refundCents <= 0) {
                continue;
            }

            $refundAmount = $refundCents / 100;
            $result = $this->paymentServiceResolver
                ->resolve($payment)
                ->refund($payment, $refundAmount);

            $payment->refunds()->updateOrCreate(
                ['provider_refund_id' => $result->refundId],
                [
                    'amount' => $refundAmount,
                    'currency' => $payment->currency ?? 'EUR',
                    'status' => $this->normalizeRefundStatus($result->status),
                ]
            );

            $remainingCents -= $refundCents;
            if ($remainingCents === 0) {
                return;
            }
        }

        if ($remainingCents > 0) {
            throw new PaymentFailedException(
                'Die Rückerstattung für Booking #'.$booking->id.' übersteigt den noch erstattbaren Zahlungsbetrag.'
            );
        }
    }

    public function refundWalletPayments(CourseBooking $booking): void
    {
        $payments = $booking->payments()
            ->where('provider', 'wallet')
            ->where('status', 'paid')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $refundedCents = (int) round(
                (float) $payment->refunds()
                    ->whereNotIn('status', ['failed', 'canceled'])
                    ->sum('amount') * 100
            );
            $availableCents = max(0, (int) round((float) $payment->amount * 100) - $refundedCents);

            if ($availableCents === 0) {
                continue;
            }

            $refundAmount = $availableCents / 100;
            $result = $this->paymentServiceResolver
                ->resolve($payment)
                ->refund($payment, $refundAmount);

            $payment->refunds()->updateOrCreate(
                ['provider_refund_id' => $result->refundId],
                [
                    'amount' => $refundAmount,
                    'currency' => $payment->currency ?? 'EUR',
                    'status' => $this->normalizeRefundStatus($result->status),
                ]
            );
        }
    }

    private function normalizeRefundStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'queued', 'pending', 'processing', 'refunded', 'failed', 'canceled' => $providerStatus,
            default => 'queued',
        };
    }
}

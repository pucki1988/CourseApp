<?php

namespace App\Services\Payments;

use App\Events\CourseBookingCreate;
use App\Events\OrderPaid;
use App\Jobs\RefundFailedBookingWalletPayments;
use App\Models\Payment\Payment;
use App\Models\Course\CourseBooking;
use App\Models\Shop\Order;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Services\Wallet\VoucherWalletService;

class PaymentProcessor
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected CourseBookingSlotService $courseBookingSlotService,
        protected VoucherWalletService $voucherWalletService,
    ) {}

    /**
     * Mark a payment as paid, update related entities, and trigger events.
     * This is provider-agnostic and can be called from any payment service.
     */
    public function handlePaid(Payment $payment): void
    {
        $alreadyPaid = $payment->isPaid();

        if (! $alreadyPaid) {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
        }

        $source = $payment->source;

        if ($source instanceof CourseBooking) {
            if ($alreadyPaid) {
                return;
            }

            $paidAmountCents = (int) round((float) $source->payments()
                ->where('status', 'paid')
                ->sum('amount') * 100);
            $bookingAmountCents = (int) round((float) $source->total_price * 100);

            if ($paidAmountCents < $bookingAmountCents) {
                return;
            }

            foreach ($source->bookingSlots as $bookingSlot) {
                $bookingSlot->update(['status' => 'booked']);
            }
            #$source->update(['payment_status' => 'paid']);
            $this->courseBookingService->refreshBookingStatus($source);

            event(new CourseBookingCreate($source));

            return;
        }

        if ($source instanceof Order) {
            if (! $source->isPaid()) {
                $source->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            event(new OrderPaid($source));
        }
    }

    /**
     * Refund (e.g. triggered directly at the provider): invalidate unredeemed vouchers of the order.
     * Idempotent, as webhooks may be delivered multiple times.
     */
    public function handleRefunded(Payment $payment): void
    {
        $source = $payment->source;

        if (! $source instanceof Order) {
            return;
        }

        $source->loadMissing('items');

        foreach ($source->items as $item) {
            if ($item->product_type !== 'sports_voucher') {
                continue;
            }

            $this->voucherWalletService->expireUnredeemedVouchers(
                collect($item->meta['vouchers'] ?? [])->pluck('id')->filter()->all()
            );
        }
    }

    public function handleFailed(Payment $payment, string $status = 'failed'): void
    {
        if ($payment->status === $status) {
            return; // idempotent
        }

        $attributes = ['status' => $status];

        if ($status === 'failed') {
            $attributes['failed_at'] = now();
        }

        if ($status === 'canceled') {
            $attributes['canceled_at'] = now();
        }

        $payment->update($attributes);

        $source = $payment->source;

        if ($source instanceof CourseBooking) {
            $paidAmountCents = (int) round((float) $source->payments()
                ->where('status', 'paid')
                ->sum('amount') * 100);
            $bookingAmountCents = (int) round((float) $source->total_price * 100);

            if ($paidAmountCents < $bookingAmountCents) {
                #$source->update(['payment_status' => $status]);

                if ($payment->provider === 'mollie') {
                    RefundFailedBookingWalletPayments::dispatch($source->id)->afterCommit();
                }
            }
        }

        if ($source instanceof Order) {
            $source->update([
                'status' => $status,
                'canceled_at' => $status === 'canceled' ? now() : $source->canceled_at,
            ]);
        }
    }
}
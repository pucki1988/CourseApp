<?php

namespace App\Services\Payments;

use App\Events\CourseBookingCreate;
use App\Events\OrderPaid;
use App\Models\Payment\Payment;
use App\Models\Course\CourseBooking;
use App\Models\Shop\Order;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;

class PaymentProcessor
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected CourseBookingSlotService $courseBookingSlotService,
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

            foreach ($source->bookingSlots as $bookingSlot) {
                $bookingSlot->update(['status' => 'booked']);
            }
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

        if ($source instanceof Order) {
            $source->update([
                'status' => $status,
                'canceled_at' => $status === 'canceled' ? now() : $source->canceled_at,
            ]);
        }
    }
}
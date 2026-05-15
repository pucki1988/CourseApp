<?php

namespace App\Services\Payments;

use App\Events\CourseBookingCreate;
use App\Models\Payment\Payment;
use App\Models\Course\CourseBooking;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;

class PaymentProcessor
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected CourseBookingSlotService $courseBookingSlotService
    ) {}

    /**
     * Mark a payment as paid, update related entities, and trigger events.
     * This is provider-agnostic and can be called from any payment service.
     */
    public function handlePaid(Payment $payment): void
    {
        // Idempotency check
        if ($payment->isPaid()) {
            return;
        }

        // Update payment status
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $source = $payment->source;

        // Update booking slots and refresh status if source is a CourseBooking
        if ($source instanceof CourseBooking) {
            foreach ($source->bookingSlots as $bookingSlot) {
                $bookingSlot->update(['status' => 'booked']);
            }
            $this->courseBookingService->refreshBookingStatus($source);

            // Trigger event
            event(new CourseBookingCreate($source));
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
    }
}

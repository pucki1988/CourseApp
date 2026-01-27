<?php

namespace App\Services\Bookings;

use App\Models\Course\CourseBooking;
use App\Events\CourseBookingPaid;

class BookingPaymentService
{
    public function setPaymentData(CourseBooking $booking,string $paymentTransactionId,string $checkoutUrl): void
    {
        $booking->update([
            'payment_transaction_id' => $paymentTransactionId,
            'checkout_url' => $checkoutUrl
        ]);
    }

    public function markPaid(CourseBooking $booking): void
    {
        if ($booking->payment_status === 'paid') {
            return; // idempotent
        }

    
        $booking->update([
            'payment_status' => 'paid'
        ]);

        event(new CourseBookingPaid($booking));
    }

    public function markFailed(CourseBooking $booking): void
    {
        if ($booking->payment_status === 'failed') {
            return;
        }

        $booking->update([
            'payment_status' => 'failed',
        ]);
    }
}
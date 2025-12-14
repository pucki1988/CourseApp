<?php

namespace App\Services\Bookings;

use App\Models\Course\CourseBooking;

class BookingPaymentService
{
    public function markPaid(CourseBooking $booking): void
    {
        if ($booking->payment_status === 'paid') {
            return; // idempotent
        }

        $booking->update([
            'payment_status' => 'paid'
        ]);
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
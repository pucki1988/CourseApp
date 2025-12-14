<?php

namespace App\Services\Bookings;

use App\Models\Course\CourseBooking;

class BookingRefundService
{
    public function createRefund(
        CourseBooking $booking,
        float $amount,
        RefundResult $refund
    ): void {
        $booking->refunds()->create([
            'payment_refund_id' => $refund->refundId,
            'amount' => $amount,
            'status' => $refund->status,
        ]);
    }

    public function markRefunded(string $refundId): void
    {
        $refund = CourseBookingRefund::where(
            'payment_refund_id',
            $refundId
        )->first();

        if (!$refund) {
            return;
        }

        $refund->update(['status' => 'refunded']);

        $booking = $refund->booking;
        if ($booking->refundedTotal() >= $booking->total_price) {
            $booking->update([
                'payment_status' => 'refunded'
            ]);
        }
    }
}
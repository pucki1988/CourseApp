<?php

namespace App\Services\Bookings;

use App\Models\Course\CourseBooking;
use App\Data\Payments\RefundResult;
use App\Models\Course\CourseBookingRefund;

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

    public function markRefunded(CourseBookingRefund $refund): void
    {
        
        $refund->update(['status' => 'completed','refunded_at' => now()]);
    }

    public function markFailed(CourseBookingRefund $refund): void
    {
        $refund->update(['status' => 'failed']);
    }

    public function markProcessing(CourseBookingRefund $refund): void
    {
        $refund->update(['status' => 'processing']);
    }
}
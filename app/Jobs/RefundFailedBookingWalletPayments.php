<?php

namespace App\Jobs;

use App\Models\Course\CourseBooking;
use App\Services\Payments\BookingPaymentRefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class RefundFailedBookingWalletPayments implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public int $bookingId,
    ) {}

    public function backoff(): array
    {
        return [60, 540, 3000, 10800, 14400];
    }

    public function handle(BookingPaymentRefundService $bookingPaymentRefundService): void
    {
        $booking = CourseBooking::findOrFail($this->bookingId);
        $bookingPaymentRefundService->refundWalletPayments($booking);
    }
}

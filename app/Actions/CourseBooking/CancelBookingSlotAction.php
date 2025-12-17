<?php

namespace App\Actions\CourseBooking;

use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use Illuminate\Support\Facades\DB;

class CancelBookingSlotAction
{
    public function __construct(
        private PaymentService $paymentService,
        private BookingRefundService $bookingRefundService,
        private CourseBookingSlotService $bookingSlotService,
        private CourseBookingService $courseBookingService
    ) {}

    public function execute(
        CourseBooking $booking,
        CourseBookingSlot $bookingSlot
    ): CourseBookingSlot {

        return DB::transaction(function () use ($booking, $bookingSlot) {

            // 1️⃣ Refund (nur wenn Preis > 0)
            if ($bookingSlot->price > 0) {
                $refund = $this->paymentService
                    ->refund($booking, $bookingSlot->price);

                $this->bookingRefundService
                    ->createRefund($booking, $bookingSlot->price, $refund);
            }

            // 2️⃣ Booking Slot stornieren
            $this->bookingSlotService->cancel($bookingSlot);

            // 3️⃣ Booking-Status aktualisieren
            $this->courseBookingService
                ->refreshBookingStatus($booking);

            return $bookingSlot->refresh();
        });
    }
}
<?php

namespace App\Actions\CourseBooking;

use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use Illuminate\Support\Facades\DB;
use App\Jobs\RefundBookingSlot;

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

        $bookingSlot = DB::transaction(function () use ($booking, $bookingSlot) {

            // 1️⃣ Booking Slot stornieren // canceled
            $this->bookingSlotService->cancel($bookingSlot);

            // 2️⃣ Booking-Status aktualisieren
            $this->courseBookingService
                ->refreshBookingStatus($booking);

            return $bookingSlot;
        });

        // 3️⃣ Refund asynchron auslösen (außerhalb der Transaction!)
        if ($bookingSlot->price > 0) {
            RefundBookingSlot::dispatch(
                $booking->id,
                $bookingSlot->id
            )->onQueue('refunds');
        }

        return $bookingSlot;
    }
}
<?php

namespace App\Actions\CourseBooking;

use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use Illuminate\Support\Facades\DB;
use App\Events\CourseBookingCanceled;
use App\Jobs\RefundBooking;


class CancelCourseBookingAction
{
    public function __construct(
        private PaymentService $paymentService,
        private BookingRefundService $bookingRefundService,
        private CourseBookingSlotService $bookingSlotService,
        private CourseBookingService $courseBookingService
    ) {}

    public function execute(
        CourseBooking $booking,
        
    ): CourseBooking {

            $booking =  DB::transaction(function () use ($booking) {

        
            // 2️⃣ Booking Slot stornieren
            $booking->bookingSlots()
                ->where('status', 'booked')
                ->each(fn ($bookingSlot) =>
                    $this->bookingSlotService->cancel($bookingSlot)
            );
            
            // 3️⃣ Booking-Status aktualisieren
            $this->courseBookingService
                ->refreshBookingStatus($booking);

            DB::afterCommit(function () use ($booking) {
                event(new CourseBookingCanceled($booking));
            });

            return $booking;
            
            });

            if ($booking->total_price > 0) {
                RefundBooking::dispatch(
                $booking->id
            )->onQueue('refunds');

                    
            }

            return $booking;
    }
}
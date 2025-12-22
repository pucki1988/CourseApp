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

        return DB::transaction(function () use ($booking) {

            // 1️⃣ Refund (nur wenn Preis > 0)
            if ($booking->total_price > 0) {
                $refund = $this->paymentService
                    ->refund($booking, $booking->total_price);

                $this->bookingRefundService
                    ->createRefund($booking, $booking->total_price, $refund);
            }

            // 2️⃣ Booking Slot stornieren
            foreach($booking->bookingSlots() as $bookingSlot){
                if($bookingSlot->status ==='booked'){
                    $this->bookingSlotService->cancel($bookingSlot);
                }
            }
            
            // 3️⃣ Booking-Status aktualisieren
            $this->courseBookingService
                ->refreshBookingStatus($booking);


            DB::afterCommit(function () use ($booking) {
                event(new CourseBookingCanceled($booking));
            });

            return $booking;
        });
    }
}
<?php

namespace App\Actions\CourseBooking;

use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use Illuminate\Support\Facades\DB;
use App\Actions\CourseBooking\CancelBookingSlotAction;
use App\Events\CourseBookingSlotCanceledByUser;

class UserCancelBookingSlotAction
{
    
    public function __construct(
        private CancelBookingSlotAction $cancelBookingSlotAction
    ) {}
    
    public function execute(
        CourseBooking $booking,
        CourseBookingSlot $bookingSlot
    ): CourseBookingSlot {
        return DB::transaction(function () use ($booking, $bookingSlot) {

            // technische Stornierung
            $this->cancelBookingSlotAction->execute($booking, $bookingSlot);
            
            $bookingSlot->refresh();
            DB::afterCommit(fn () =>
                event(new CourseBookingSlotCanceledByUser(
                    $bookingSlot
                ))
            );

            return $bookingSlot;
        });
    }
}
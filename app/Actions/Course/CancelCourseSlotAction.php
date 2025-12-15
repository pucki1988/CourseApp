<?php

namespace App\Actions\Course;

use App\Actions\CourseBooking\CancelBookingSlotAction;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseSlot;
use Illuminate\Support\Facades\DB;

class CancelCourseSlotAction
{
    public function __construct(
        private CancelBookingSlotAction $cancelBookingSlotAction
    ) {}

    public function execute(CourseSlot $slot): CourseSlot
    {
        return DB::transaction(function () use ($slot) {

            // 1️⃣ Alle gebuchten Slots stornieren
            $slot->bookingSlots()
                ->where('status', 'booked')
                ->get()
                ->each(function (CourseBookingSlot $bookingSlot) {
                    $this->cancelBookingSlotAction->execute(
                        $bookingSlot->booking,
                        $bookingSlot
                    );
                });

            // 2️⃣ Slot selbst absagen
            $slot->update([
                'status' => 'canceled'
            ]);

            // 3️⃣ Domain Event (optional, aber perfekt hier)
            #event(new CourseSlotCancelled($slot));

            return $slot->refresh();
        });
    }
}
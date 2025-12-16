<?php

namespace App\Actions\Course;

use App\Actions\CourseBooking\CancelBookingSlotAction;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseSlot;
use Illuminate\Support\Facades\DB;
use App\Events\CourseSlotCanceled;

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

            DB::afterCommit(function () use ($slot) {
                event(new CourseSlotCanceled($slot));
            });

            return $slot->refresh();
        });
    }
}
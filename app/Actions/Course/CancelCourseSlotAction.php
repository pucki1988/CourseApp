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

    public function execute(CourseSlot $slot,?string $reason = null): CourseSlot
    {
        //Absage des Slot durch Verantwortlichen
        return DB::transaction(function () use ($reason, $slot) {

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

            // 2️⃣ Course Slot selbst absagen
            $slot->update([
                'status' => 'canceled'
            ]);

            $message_reason = $reason ?? 'Der Verantwortliche hat den Termin des Kurs abgesagt.';

            DB::afterCommit(function () use ($message_reason, $slot) {
                event(new CourseSlotCanceled($slot,$message_reason));
            });

            return $slot->refresh();
        });
    }
}
<?php

namespace App\Actions\Course;

use App\Actions\CourseBooking\CancelCourseBookingAction;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseSlot;
use Illuminate\Support\Facades\DB;
use App\Events\CourseCanceled;

class CancelCourseAction
{
    public function __construct(
        private CancelCourseBookingAction $cancelCourseBookingAction
    ) {}

    public function execute(CourseSlot $slot,?string $reason = null): CourseSlot
    {
        //Absage des Slot durch Verantwortlichen
        return DB::transaction(function () use ($reason, $slot) {

            // 1️⃣ Alle betroffenen CourseBookings EINMAL stornieren
            $slot->bookingSlots()
                ->where('status', 'booked')
                ->with('booking')
                ->get()
                ->pluck('booking')
                ->unique('id')
                ->each(function (CourseBooking $booking) {
                    $this->cancelCourseBookingAction->execute($booking);
                });

            // 2️⃣ Alle Slots des Kurses absagen
            $slot->course
                ->slots()
                ->where('status', 'active')
                ->update([
                    'status' => 'canceled',
                ]);

            $message_reason = $reason ?? 'Der Verantwortliche hat den Termin des Kurs abgesagt.';

            DB::afterCommit(function () use ($message_reason, $slot) {
                event(new CourseCanceled($slot,$message_reason));
            });

            return $slot->refresh();
        });
    }
}
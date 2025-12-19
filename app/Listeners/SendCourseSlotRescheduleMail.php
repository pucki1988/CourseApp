<?php

namespace App\Listeners;

use App\Events\CourseSlotRescheduled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\CourseSlotRescheduledMail;

class SendCourseSlotRescheduleMail implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CourseSlotRescheduled $event): void
    {
        $event->slot->bookingSlots()
            ->with('booking.user','booking.course')
            ->whereIn('status', ['booked'])
            ->get()
            ->each(function ($bookingSlot) use ($event) {

                $user = $bookingSlot->booking->user;

                if (!$user || !$user->email) {
                    return;
                }

                Mail::to($user->email)->send(
                    new CourseSlotRescheduledMail(
                        $event->slot,
                        $event->oldData
                    )
                );
            });
    }
}

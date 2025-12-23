<?php

namespace App\Listeners;

use App\Events\CourseSlotCanceled;
use App\Mail\CourseSlotCanceledMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCourseSlotCanceledMail implements ShouldQueue
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
    public function handle(CourseSlotCanceled $event): void
    {

       
        // Alle gebuchten BookingSlots laden
        $event->slot->bookingSlots()
            ->with('booking.user','booking.course')
            ->whereIn('status', ['refunded','canceled'])
            ->get()
            ->each(function ($bookingSlot) use ($event) {

                $user = $bookingSlot->booking->user;

                if (!$user || !$user->email) {
                    return;
                }

                $reason = $event->reason;

                Mail::to($user->email)->send(
                    new CourseSlotCanceledMail(
                        $event->slot,
                        $bookingSlot->booking,
                        $reason
                    )
                );
            });
    }
}

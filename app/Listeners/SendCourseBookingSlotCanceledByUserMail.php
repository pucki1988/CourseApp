<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\CourseBookingSlotCanceledByUser;
use App\Mail\CourseBookingSlotCanceledByUserToUserMail;

class SendCourseBookingSlotCanceledByUserMail implements ShouldQueue
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
    public function handle(CourseBookingSlotCanceledByUser $event): void
    {
        $bookingSlot = $event->bookingSlot->load([
            'booking.user',
            'booking.course',
            'slot',
        ]);

        $user = $bookingSlot->booking?->user;

        if (!$user || !$user->email) {
            return;
        }

        Mail::to($user->email)->send(
            new CourseBookingSlotCanceledByUserToUserMail(
                $bookingSlot,
                $bookingSlot->booking
            )
        );
    }
}

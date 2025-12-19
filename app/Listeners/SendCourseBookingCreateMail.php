<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\CourseBookingCreate;
use Illuminate\Support\Facades\Mail;
use App\Mail\CourseBookingCreateMail;


class SendCourseBookingCreateMail  implements ShouldQueue
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
    public function handle(CourseBookingCreate $event): void
    {
        // Alle gebuchten BookingSlots laden
        $booking=$event->booking->load([
            'bookingSlots.slot',
            'course',
            'user',
        ]);
            $user = $booking?->user;

                Mail::to($user->email)->send(
                    new CourseBookingCreateMail(
                        $booking
                    )
                );
            
    }
}

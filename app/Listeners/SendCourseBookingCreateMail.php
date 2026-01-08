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

    public $delay = 60; // Sekunden
    /**
     * Handle the event.
     */
    public function handle(CourseBookingCreate $event): void
    {

        if (! in_array($event->booking->payment_status, ['paid', 'open', 'pending'], true)) {
            return;
        }

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

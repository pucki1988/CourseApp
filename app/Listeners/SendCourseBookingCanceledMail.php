<?php

namespace App\Listeners;

use App\Events\CourseBookingCanceled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\CourseBookingCanceledMail;

class SendCourseBookingCanceledMail implements ShouldQueue
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
    public function handle(CourseBookingCanceled $event): void
    {
        $booking=$event->booking->load([
            'bookingSlots.slot',
            'course',
            'user',
        ]);

        $user = $booking->user;

        if (!$user || !$user->email) {
            return;
        }

        Mail::to($user->email)->send(
            new CourseBookingCanceledMail(
                $booking
            )
        );
            
    }
}

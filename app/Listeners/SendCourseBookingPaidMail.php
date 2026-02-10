<?php

namespace App\Listeners;

use App\Events\CourseBookingPaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\CourseBookingPaidMail;

class SendCourseBookingPaidMail implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }


    public $delay = 120; // Sekunden
    /**
     * Handle the event.
     */
    public function handle(CourseBookingPaid $event): void
    {
        $user = $event->booking->user;

        Mail::to($user->email)
            ->send(new CourseBookingPaidMail($event->booking));
    }
}

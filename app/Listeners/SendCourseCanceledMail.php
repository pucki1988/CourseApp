<?php

namespace App\Listeners;

use App\Events\CourseCanceled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\CourseCanceledMail;

class SendCourseCanceledMail implements ShouldQueue
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
    public function handle(CourseCanceled $event): void
    {
        $event->slot->bookingSlots()
            ->with('booking.user', 'booking.course')
            ->whereIn('status', ['refunded', 'canceled'])
            ->get()
            ->pluck('booking')
            ->unique('id')
            ->each(function ($booking) use ($event) {

                $user = $booking->user;

                if (!$user || !$user->email) {
                    return;
                }

                $reason = $event->reason;

                Mail::to($user->email)->send(
                    new CourseCanceledMail(
                        $event->slot->course,
                        $booking,
                        $reason
                    )
                );
            });
    }
}

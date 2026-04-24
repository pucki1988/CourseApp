<?php

namespace App\Listeners;

use App\Events\CourseBookingCreate;
use App\Notifications\CourseBookingCreatedWebPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCourseBookingCreateWebPush implements ShouldQueue
{
    public function handle(CourseBookingCreate $event): void
    {
        if (! in_array($event->booking->payment_status, ['paid', 'open', 'pending'], true)) {
            return;
        }

        $booking = $event->booking->loadMissing(['course', 'user']);
        $user = $booking->user;

        if (! $user || $user->pushSubscriptions()->doesntExist()) {
            return;
        }

        $bookingId= (string) ($event->booking->id ?? '0');

        $user->notify(new CourseBookingCreatedWebPushNotification($bookingId));
    }
}

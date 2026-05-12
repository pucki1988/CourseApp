<?php

namespace App\Listeners;

use App\Events\CourseBookingCreate;
use App\Notifications\CourseBookingCreatedWebPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCourseBookingCreateWebPush implements ShouldQueue
{
    public function handle(CourseBookingCreate $event): void
    {
        $booking = $event->booking->loadMissing(['payment', 'course', 'user']);
        $paymentStatus = $booking->payment?->status ?? $booking->payment_status;

        if (! in_array($paymentStatus, ['paid', 'open', 'pending'], true)) {
            return;
        }

        $user = $booking->user;

        if (! $user || $user->pushSubscriptions()->doesntExist()) {
            return;
        }

        $bookingId= (string) ($event->booking->id ?? '0');

        $user->notify(new CourseBookingCreatedWebPushNotification($bookingId));
    }
}

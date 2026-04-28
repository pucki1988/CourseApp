<?php

namespace App\Listeners;

use App\Events\CourseBookingSlotCanceledByUser;
use App\Notifications\CourseBookingSlotCanceledWebPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCourseBookingSlotCanceledWebPush implements ShouldQueue
{
    public function handle(CourseBookingSlotCanceledByUser $event): void
    {
        $bookingSlot = $event->bookingSlot->loadMissing([
            'booking.user',
            'booking.course',
        ]);

        $user = $bookingSlot->booking?->user;

        if (! $user || $user->pushSubscriptions()->doesntExist()) {
            return;
        }

        $slotDate = (string) ($bookingSlot->slot->date->format('d.m.Y') . ' | ' . ($bookingSlot->slot->start_time?->format('H:i') ?? 'Unbekannt'));

        $user->notify(new CourseBookingSlotCanceledWebPushNotification($slotDate));
    }
}

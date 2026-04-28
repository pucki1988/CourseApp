<?php

namespace App\Listeners;

use App\Events\CourseSlotCanceled;
use App\Notifications\CourseSlotCanceledWebPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCourseSlotCanceledWebPush implements ShouldQueue
{
    public function handle(CourseSlotCanceled $event): void
    {
        $event->slot->bookingSlots()
            ->with('booking.user', 'booking.course')
            ->whereIn('status', ['refunded', 'canceled'])
            ->get()
            ->each(function ($bookingSlot) use ($event) {
                $user = $bookingSlot->booking?->user;

                if (! $user || $user->pushSubscriptions()->doesntExist()) {
                    return;
                }

                $slotDate = (string) ($event->slot->date->format('d.m.Y') . ' | ' . ($event->slot->start_time?->format('H:i') ?? 'Unbekannt'));
                #$courseTitle = (string) ($bookingSlot->booking?->course?->title ?? 'deinem Kurs');

                $user->notify(new CourseSlotCanceledWebPushNotification($slotDate, $event->reason));
            });
    }
}

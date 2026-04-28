<?php

namespace App\Listeners;

use App\Events\CourseSlotRescheduled;
use App\Notifications\CourseSlotRescheduledWebPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCourseSlotRescheduleWebPush implements ShouldQueue
{
    public function handle(CourseSlotRescheduled $event): void
    {
        $newDate = $event->slot->start_time
            ? \Carbon\Carbon::parse($event->slot->start_time)->format('d.m.Y H:i') . ' Uhr'
            : 'einem neuen Termin';

        $oldSlotDate =  ($event->oldData["date"]->format('d.m.Y') ?? 'Unbekannt') . ' | ' . ($event->oldData["start_time"]?->format('H:i') ?? 'Unbekannt');

        

        $event->slot->bookingSlots()
            ->with('booking.user', 'booking.course')
            ->whereIn('status', ['booked'])
            ->get()
            ->each(function ($bookingSlot) use ($event, $newDate, $oldSlotDate) {
                $user = $bookingSlot->booking?->user;

                if (! $user || $user->pushSubscriptions()->doesntExist()) {
                    return;
                }

                $user->notify(new CourseSlotRescheduledWebPushNotification($oldSlotDate, $newDate));
            });
    }
}

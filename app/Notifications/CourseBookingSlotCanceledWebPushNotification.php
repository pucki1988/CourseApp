<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CourseBookingSlotCanceledWebPushNotification extends Notification
{
    public function __construct(private readonly string $slotDate)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Termin deines Kurses storniert')
            ->body('Die Stornierung für den Termin "' . $this->slotDate . '" wurde erfolgreich durchgeführt.')
            ->tag('course-booking-slot-canceled')
            ->icon('/modules/mod_courseapp/tmpl/pwa/icon-192.png');
    }
}

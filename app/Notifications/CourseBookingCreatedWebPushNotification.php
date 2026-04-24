<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CourseBookingCreatedWebPushNotification extends Notification
{
    public function __construct(private readonly string $bookingId)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Buchung bestaetigt')
            ->body('Neue Buchung #"' . $this->bookingId . '" für einen Kurs.')
            ->tag('course-booking-created')
            ->icon('/modules/mod_courseapp/tmpl/pwa/icon-192.png');
    }
}

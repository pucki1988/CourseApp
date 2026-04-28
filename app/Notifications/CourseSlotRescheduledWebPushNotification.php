<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CourseSlotRescheduledWebPushNotification extends Notification
{
    public function __construct(
        private readonly string $oldDate,
        private readonly string $newDate,
    ) {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Kurstermin verschoben')
            ->body('Der Kurstermin wurde von ' . $this->oldDate . ' auf ' . $this->newDate . ' verschoben.')
            ->tag('course-slot-rescheduled')
            ->icon('/modules/mod_courseapp/tmpl/pwa/icon-192.png');
    }
}

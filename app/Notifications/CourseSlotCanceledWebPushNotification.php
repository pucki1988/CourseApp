<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CourseSlotCanceledWebPushNotification extends Notification
{
    public function __construct(
        private readonly string $courseTitle,
        private readonly string $reason,
    ) {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Kurstermin abgesagt')
            ->body('"' . $this->courseTitle . '" wurde abgesagt: ' . $this->reason)
            ->tag('course-slot-canceled')
            ->icon('/modules/mod_courseapp/tmpl/pwa/icon-192.png');
    }
}

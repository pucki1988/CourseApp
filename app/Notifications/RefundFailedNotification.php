<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public int $bookingId,
        public int $bookingSlotId,
        public string $reason)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rückerstattung fehlgeschlagen')
            ->greeting('Hallo Admin,')
            ->line('Ein Rückerstattung konnte nach mehreren Versuchen nicht durchgeführt werden.')
            ->line('Booking-ID: ' . $this->bookingId)
            ->line('Slot-ID: ' . $this->bookingSlotId)
            ->line('Fehler: ' . $this->reason)
            ->line('Bitte prüfe den Vorgang manuell.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

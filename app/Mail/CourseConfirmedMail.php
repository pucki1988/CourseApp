<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use App\Models\Course\CourseSlot;
use App\Models\User;

class CourseConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CourseSlot $slot, public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kurs bestätigt — Mindestteilnehmer erreicht',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.courses.course-confirmed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

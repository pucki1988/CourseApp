<?php

namespace App\Mail;

use App\Models\News\NewsItem;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NewsItem $newsItem, public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->newsItem->is_important
                ? $this->newsItem->title
                : $this->newsItem->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.news.published',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

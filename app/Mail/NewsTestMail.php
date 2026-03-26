<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $newsMessage,
        public bool $isImportant,
        public string $publishedAt,
        public array $newsTags = [],
        public ?string $areaName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[TEST] ' . $this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.news.test',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

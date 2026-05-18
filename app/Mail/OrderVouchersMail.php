<?php

namespace App\Mail;

use App\Models\Shop\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderVouchersMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public array $vouchers,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deine Gutscheincodes zur Bestellung #'.$this->order->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shop.order-vouchers',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
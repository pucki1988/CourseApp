<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Shop\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
        return [
            Attachment::fromData(
                fn () => $this->generatePdf(),
                'gutscheine-bestellung-'.$this->order->id.'.pdf',
            )->withMime('application/pdf'),
        ];
    }

    private function generatePdf(): string
    {
        return Pdf::loadView('pdf.shop.order-vouchers', [
            'order' => $this->order,
            'vouchers' => $this->vouchers,
        ])->setPaper('a4')->output();
    }
}
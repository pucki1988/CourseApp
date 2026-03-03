<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CoachMonthlyBillingMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $billingData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $billingData)
    {
        $this->billingData = $billingData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Monatsabrechnung ' . $this->billingData['month_name'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.coaches.monthly-billing',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $coachName = Str::slug($this->billingData['coach']->name ?? 'trainer');
        $filename = sprintf(
            'monatsabrechnung-%s-%s.pdf',
            $this->billingData['year'] . '-' . str_pad((string) $this->billingData['month'], 2, '0', STR_PAD_LEFT),
            $coachName
        );

        return [
            Attachment::fromData(fn () => $this->generatePdf(), $filename)
                ->withMime('application/pdf'),
        ];
    }

    private function generatePdf(): string
    {
        return Pdf::loadView('pdf.coaches.monthly-billing', [
            'billingData' => $this->billingData,
        ])->setPaper('a4')->output();
    }
}

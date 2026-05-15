<?php

namespace App\Services\Payments;

use Mollie\Laravel\Facades\Mollie;
use App\Contracts\PaymentService;
use App\Data\Payments\PaymentResult;
use App\Data\Payments\RefundResult;
use App\Models\Course\CourseBooking;
use App\Models\Payment\Payment;
use App\Models\Payment\Refund;
use App\Services\Bookings\BookingPaymentService;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Exceptions\PaymentFailedException;
use App\Services\Loyalty\LoyaltyPointService;

class MolliePaymentService implements PaymentService
{
    public function __construct(
        protected PaymentProcessor $paymentProcessor,
        protected BookingPaymentService $bookingPaymentService,
        protected CourseBookingService $courseBookingService,
        protected CourseBookingSlotService $courseBookingSlotService,
        protected LoyaltyPointService $loyaltyPointService
    ) {}

    public function createPayment(Payment $payment): PaymentResult
    {
        $source = $payment->source;

        $metadata = [
            'payment_id' => $payment->id,
        ];

        // Beschreibung und Redirect-URLs je nach Quell-Typ
        if ($source instanceof CourseBooking) {
            $description = 'Sportkurse - Buchung '.$source->id;
            $redirectUrl = 'https://djk-sg-schoenbrunn.de/sportkurse?bookingId='.$source->id.'&success=true';
            $cancelUrl   = 'https://djk-sg-schoenbrunn.de/sportkurse?bookingId='.$source->id.'&success=false';
            $metadata['booking_id'] = $source->id;
        } else {
            $description = 'Bestellung #'.$payment->id;
            $redirectUrl = 'https://djk-sg-schoenbrunn.de/shop?paymentId='.$payment->id.'&success=true';
            $cancelUrl   = 'https://djk-sg-schoenbrunn.de/shop?paymentId='.$payment->id.'&success=false';
        }

        $webhookUrl = config('services.mollie.webhook_url_dev') ?: route('webhooks.mollie');

        // Mollie-Zahlung erzeugen — metadata enthält nur die lokale payment_id
        $molliePayment = Mollie::api()->payments->create([
            'amount' => [
                'currency' => $payment->currency ?? 'EUR',
                'value'    => number_format((float) $payment->amount, 2, '.', ''),
            ],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'cancelUrl'   => $cancelUrl,
            'webhookUrl'  => $webhookUrl,
            'metadata'    => $metadata,
        ]);

        // Payment-Record mit Provider-Daten aktualisieren
        $payment->update([
            'provider_payment_id' => $molliePayment->id,
            'checkout_url'        => $molliePayment->getCheckoutUrl(),
            'method'              => $molliePayment->method ?? $payment->method,
            'status'              => 'open',
        ]);

        return new PaymentResult(
            provider:      'mollie',
            transactionId: $molliePayment->id,
            checkoutUrl:   $molliePayment->getCheckoutUrl(),
            status:        'open',
            method:        $molliePayment->method,
        );
    }

    public function refund(Payment $payment, float $amount): RefundResult
    {
        try {
            // Neue Zahlungen: provider_payment_id direkt am Payment-Record
            // Legacy-Zahlungen: transaction_id am Booking-Source-Objekt
            $providerPaymentId = $payment->provider_payment_id
                ?? ($payment->source?->payment_transaction_id ?? null);

            if (!$providerPaymentId) {
                throw new PaymentFailedException(
                    'Keine Provider-Payment-ID für Payment #'.$payment->id.' vorhanden.'
                );
            }

            $molliePayment = Mollie::api()->payments->get($providerPaymentId);

            $metadata = [
                'payment_id' => $payment->id,
            ];

            if ($payment->source instanceof CourseBooking) {
                $metadata['booking_id'] = $payment->source->id;
            }

            $refund = $molliePayment->refund([
                'amount' => [
                    'currency' => $payment->currency ?? 'EUR',
                    'value'    => number_format($amount, 2, '.', ''),
                ],
                'metadata' => $metadata,
            ]);

            return new RefundResult(
                refundId: $refund->id,
                status:   $refund->status,
            );
        } catch (PaymentFailedException $e) {
            throw $e; // direkt weiterwerfen, nicht einwickeln
        } catch (\Throwable $e) {
            throw new PaymentFailedException(
                'Die Rückerstattung konnte nicht durchgeführt werden.'
            );
        }
    }

    public function handleWebhook(string $providerPaymentId): void
    {
        $molliePayment = Mollie::api()->payments->get($providerPaymentId);
        $hasRefunds = $molliePayment->hasRefunds();

        // ----- Neuer Weg: lokale payment_id in Metadata -----
        $localPaymentId = $molliePayment->metadata->payment_id ?? null;

        $localPayment = null;

        if ($localPaymentId) {
            $localPayment = Payment::find($localPaymentId);
        }

        if ($localPayment) {

            // Method can be null right after creation and appear later once user chose one.
            $localPayment->update([
                'method' => $molliePayment->method ?? $localPayment->method,
            ]);

            if ($hasRefunds) {
                $this->syncRefundState($localPayment, $molliePayment);
            }

            match (true) {
                $molliePayment->isPaid() && ! $hasRefunds => $this->paymentProcessor->handlePaid($localPayment),
                $molliePayment->isFailed()  => $this->paymentProcessor->handleFailed($localPayment, 'failed'),
                $molliePayment->isCanceled() => $this->paymentProcessor->handleFailed($localPayment, 'canceled'),
                $molliePayment->isExpired() => $this->paymentProcessor->handleFailed($localPayment, 'expired'),
                default                     => null,
            };

            return;
        }

        // ----- Legacy-Fallback: booking_id (alte Zahlungen oder fehlender lokaler Record) -----
        $bookingId = $molliePayment->metadata->booking_id ?? null;

        if (!$bookingId) {
            return;
        }

        $booking = CourseBooking::find($bookingId);

        if (! $booking) {
            return;
        }

        $legacyLocalPayment = $booking->payment;

        if (! $legacyLocalPayment || ! $hasRefunds) {
            return;
        }

        $this->syncRefundState($legacyLocalPayment, $molliePayment);

    }

    private function syncRefundState(Payment $payment, $molliePayment): void
    {
        $processedProviderIds = [];

        foreach ($molliePayment->refunds() as $mollieRefund) {
            $processedProviderIds[] = $mollieRefund->id;

            /** @var Refund|null $localRefund */
            $localRefund = $payment->refunds()
                ->where('provider_refund_id', $mollieRefund->id)
                ->first();

            if (! $localRefund instanceof Refund) {
                $localRefund = $payment->refunds()->create([
                    'amount' => (float) ($mollieRefund->amount->value ?? 0),
                    'currency' => $mollieRefund->amount->currency ?? 'EUR',
                    'provider_refund_id' => $mollieRefund->id,
                    'status' => 'queued',
                ]);
            }

            $newStatus = match ($mollieRefund->status) {
                'pending' => 'pending',
                'processing' => 'processing',
                'refunded' => 'refunded',
                'failed' => 'failed',
                'canceled' => 'canceled',
                default => $localRefund->status,
            };

            $localRefund->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === 'refunded' ? now() : null,
                'failed_at' => $newStatus === 'failed' ? now() : null,
                'canceled_at' => $newStatus === 'canceled' ? now() : null,
            ]);
        }

        if (count($processedProviderIds) === 0) {
            return;
        }

        $payment->refunds()
            ->whereNotIn('provider_refund_id', $processedProviderIds)
            ->whereNotIn('status', ['refunded', 'failed', 'canceled'])
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
    }


}


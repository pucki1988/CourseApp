<?php

namespace App\Services\Payments;

use Mollie\Laravel\Facades\Mollie;
use App\Contracts\PaymentService;
use App\Data\Payments\PaymentResult;
use App\Data\Payments\RefundResult;
use App\Models\Course\CourseBooking;
use App\Models\Payment\Payment;
use App\Services\Bookings\BookingPaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Exceptions\PaymentFailedException;
use App\Services\Loyalty\LoyaltyPointService;

class MolliePaymentService implements PaymentService
{
    public function __construct(
        protected BookingPaymentService $bookingPaymentService,
        protected BookingRefundService $bookingRefundService,
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

            $source = $localPayment->source;

            if ($molliePayment->hasRefunds() && $source instanceof CourseBooking) {
                foreach ($molliePayment->refunds() as $refund) {
                    $this->handleRefund($refund, $source);
                }

                $this->syncRefundState($localPayment, $molliePayment);
            }

            match (true) {
                $molliePayment->isPaid()    => $this->handlePaid($localPayment),
                $molliePayment->isFailed()  => $this->handleFailed($localPayment, 'failed'),
                $molliePayment->isCanceled() => $this->handleFailed($localPayment, 'canceled'),
                $molliePayment->isExpired() => $this->handleFailed($localPayment, 'expired'),
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

        if (!$booking) {
            return;
        }

        if ($molliePayment->hasRefunds()) {
            foreach ($molliePayment->refunds() as $refund) {
                $this->handleRefund($refund, $booking);
            }
        }

        match (true) {
            $molliePayment->isPaid()    => $this->handlePaidLegacy($booking),
            $molliePayment->isFailed(),
            $molliePayment->isCanceled(),
            $molliePayment->isExpired() => $this->handleFailedLegacy($booking),
            default                     => null,
        };
    }

    // ---- Neuer Weg (über lokales Payment) -----------------------------------

    private function handlePaid(Payment $payment): void
    {
        if ($payment->isPaid()) {
            return; // idempotent
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $source = $payment->source;

        if ($source instanceof CourseBooking) {
            $this->bookingPaymentService->markPaid($source);
            foreach ($source->bookingSlots as $bookingSlot) {
                $bookingSlot->update(['status' => 'booked']);
            }
            $this->courseBookingService->refreshBookingStatus($source);
        }
    }

    private function handleFailed(Payment $payment, string $status = 'failed'): void
    {
        if ($payment->status === $status) {
            return; // idempotent
        }

        $attributes = ['status' => $status];

        if ($status === 'failed') {
            $attributes['failed_at'] = now();
        }

        if ($status === 'canceled') {
            $attributes['canceled_at'] = now();
        }

        $payment->update($attributes);

        $source = $payment->source;

        if ($source instanceof CourseBooking) {
            $this->handleFailedLegacy($source);
        }
    }

    private function syncRefundState(Payment $payment, $molliePayment): void
    {
        $hasRefunded = false;
        $hasProcessing = false;
        $hasFailed = false;

        foreach ($molliePayment->refunds() as $refund) {
            if ($refund->status === 'refunded') {
                $hasRefunded = true;
            }

            if ($refund->status === 'processing') {
                $hasProcessing = true;
            }

            if ($refund->status === 'failed') {
                $hasFailed = true;
            }
        }

        if ($hasRefunded) {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);
            return;
        }

        if ($hasProcessing) {
            $payment->update([
                'status' => 'refund_processing',
            ]);
            return;
        }

        if ($hasFailed) {
            $payment->update([
                'status' => 'refund_failed',
            ]);
        }
    }

    // ---- Legacy-Weg (direktes Booking ohne lokalen Payment-Record) -----------

    private function handlePaidLegacy(CourseBooking $booking): void
    {
        if ($booking->payment_status === 'paid') {
            return;
        }

        $this->bookingPaymentService->markPaid($booking);
        foreach ($booking->bookingSlots as $bookingSlot) {
            $bookingSlot->update(['status' => 'booked']);
        }
        $this->courseBookingService->refreshBookingStatus($booking);
    }

    private function handleFailedLegacy(CourseBooking $booking): void
    {
        $this->bookingPaymentService->markFailed($booking);

        if ($booking->redeemed_points > 0 && !$booking->points_restored && $booking->user && $booking->user->loyaltyAccount) {
            $this->loyaltyPointService->earn(
                $booking->user->loyaltyAccount,
                $booking->redeemed_points,
                'earn',
                'sport',
                $booking,
                'Zahlung von Buchung fehlgeschlagen - Rückerstattung von Treuepunkten'
            );
            $booking->update(['points_restored' => true]);
            $booking->refresh();
        }

        foreach ($booking->bookingSlots as $bookingSlot) {
            $this->courseBookingSlotService->cancel($bookingSlot);
        }

        $this->courseBookingService->refreshBookingStatus($booking);
    }

    // ---- Refund-Handling (Booking-seitig, unverändert) ----------------------

    protected function handleRefund($refund, CourseBooking $booking): void
    {
        $localRefund = $booking->refunds()
            ->where('payment_refund_id', $refund->id)
            ->first();

        if (!$localRefund) {
            return; // idempotent
        }

        match ($refund->status) {
            'refunded'   => $this->bookingRefundService->markRefunded($localRefund),
            'processing' => $this->bookingRefundService->markProcessing($localRefund),
            'failed'     => $this->bookingRefundService->markFailed($localRefund),
            default      => null,
        };
    }
}


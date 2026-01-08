<?php

namespace App\Services\Payments;

use Mollie\Laravel\Facades\Mollie;
use App\Contracts\PaymentService;
use App\Data\Payments\PaymentResult;
use App\Data\Payments\RefundResult;
use App\Models\Course\CourseBooking;
use App\Services\Bookings\BookingPaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;

use App\Exceptions\PaymentFailedException;

class MolliePaymentService implements PaymentService
{

    public function __construct(
        protected BookingPaymentService $bookingPaymentService,
        protected BookingRefundService $bookingRefundService,
        protected CourseBookingService $courseBookingService,
        protected CourseBookingSlotService $courseBookingSlotService
    ) {}

    public function createPayment(CourseBooking $booking): PaymentResult
    {


            // Mollie SDK
            // Payment erzeugen
            $payment = Mollie::api()->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => number_format($booking->total_price, 2, '.', '') // You must send the correct number of decimals, thus we enforce the use of strings
            ],
            "description" => "Buchung ".$booking->id,
            "redirectUrl" => 'https://djk-sg-schoenbrunn.de/sportkurse?bookingId='.$booking->id.'&success=true',
            "cancelUrl" => 'https://djk-sg-schoenbrunn.de/sportkurse?bookingId='.$booking->id.'&success=false',
            "webhookUrl" => route('webhooks.mollie'),
            #"webhookUrl" => 'https://djk-sg-schoenbrunn.de/sportkurse',
            "metadata" => [
                "booking_id" => $booking->id,
            ],
            ]);
            return new PaymentResult(
                provider: 'mollie',
                transactionId: $payment->id,
                checkoutUrl: $payment->getCheckoutUrl(),
                status: 'open'
            );
    }

    public function refund(CourseBooking $booking,float $amount): RefundResult{
        
        
        try {
        $payment = Mollie::api()->payments->get(
            $booking->payment_transaction_id
        );

        $refund = $payment->refund([
            'amount' => [
                'currency' => 'EUR',
                'value' => number_format($amount, 2, '.', ''),
            ],
            'metadata' => [
                'booking_id' => $booking->id,
            ],
        ]);

        return new RefundResult(
            refundId: $refund->id,
            status: $refund->status
        );

        } catch (\Throwable $e) {

            throw new PaymentFailedException(
                'Die Rückerstattung konnte nicht durchgeführt werden.'
            );
        }
    }

    public function handleWebhook(string $paymentId): void
    {
        $payment = Mollie::api()->payments->get($paymentId);

        $bookingId = $payment->metadata->booking_id ?? null;

        if (!$bookingId) {
            return;
        }

        $booking = CourseBooking::find($bookingId);
       
        if (!$booking) {
            return;
        }


        if ($payment->hasRefunds()) {
            foreach ($payment->refunds() as $refund) {
                $this->handleRefund($refund, $booking);
            }
        }

        match (true) {
            $payment->isPaid() =>
                $this->handlePaid($booking),
            $payment->isFailed(),
            $payment->isCanceled(),
            $payment->isExpired() =>
                $this->handleFailed($booking),
            default => null,
        };
    }

    private function handlePaid(CourseBooking $booking): void
    {
        $this->bookingPaymentService->markPaid($booking);
        $this->courseBookingService->refreshBookingStatus($booking);
    }

    private function handleFailed(CourseBooking $booking): void
    {
        $this->bookingPaymentService->markFailed($booking);
        foreach($booking->bookingSlots as $bookingSlot ){
            $this->courseBookingSlotService->cancel($bookingSlot);
        }
        
        $this->courseBookingService->refreshBookingStatus($booking);
    }

    protected function handleRefund($refund, CourseBooking $booking): void
    {
        $localRefund = $booking->refunds()
            ->where('payment_refund_id', $refund->id)
            ->first();

        if (!$localRefund) {
            return; // idempotent
        }

        match ($refund->status) {
            'refunded' =>
                $this->bookingRefundService->markRefunded($localRefund),
            'processing' =>
                $this->bookingRefundService->markProcessing($localRefund),
            'failed' =>
                $this->bookingRefundService->markFailed($localRefund),

            default => null,
        };
    }
}


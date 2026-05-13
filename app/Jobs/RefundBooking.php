<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Exceptions\PaymentFailedException;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseBooking;
use App\Models\Payment\Payment;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Course\CourseBookingSlotService;
use App\Services\Course\CourseBookingService;
use App\Services\Loyalty\LoyaltyPointService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Illuminate\Support\Facades\Notification;
use App\Notifications\RefundFailedNotification;

class RefundBooking implements ShouldQueue
{
    use Queueable;

    public int $bookingId;


    /**
     * Retry-Strategie
     */
    public int $tries = 5;
    public function backoff(): array
    {
        return [
            60,       // 1 Minute
            540,      // 10 Minuten
            3000,     // 60 Minuten
            10800,    // 4 Stunden
            14400,    // 8 Stunden
        ];
    }
    /**
     * Create a new job instance.
     */
    public function __construct(int $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService,
        BookingRefundService $bookingRefundService,
        CourseBookingSlotService $bookingSlotService,
        CourseBookingService $courseBookingService,
        LoyaltyPointService $loyaltyPointService): void
    {
        $booking = CourseBooking::find($this->bookingId);
        $booking->loadMissing('payment');
        
        try {
            // 1) Neuer Weg: genau ein Payment pro Booking
            $localPayment = $booking?->payment;

            // 2) Legacy-Fallback: exakt die gespeicherte Provider-Transaktion
            if (! $localPayment && $booking->payment_transaction_id) {
                $localPayment = Payment::where('source_type', CourseBooking::class)
                    ->where('source_id', $booking->id)
                    ->where('provider', 'mollie')
                    ->where('provider_payment_id', $booking->payment_transaction_id)
                    ->first();
            }

            // 3) Fallback: letzte als paid markierte Mollie-Zahlung
            if (! $localPayment) {
                $localPayment = Payment::where('source_type', CourseBooking::class)
                    ->where('source_id', $booking->id)
                    ->where('provider', 'mollie')
                    ->where('status', 'paid')
                    ->first();
            }

            // 4) Letzter Fallback: irgendein lokaler Payment-Record
            if (! $localPayment) {
                $localPayment = Payment::where('source_type', CourseBooking::class)
                    ->where('source_id', $booking->id)
                    ->first();
            }

            // Für alte Buchungen ohne Payment-Record: synthetischen Record aus Legacy-Feld erzeugen
            if (!$localPayment && $booking->payment_transaction_id) {
                $localPayment = $booking->payment()->create([
                    'amount'               => $booking->total_price,
                    'currency'             => 'EUR',
                    'method'               => 'pending',
                    'provider'             => 'mollie',
                    'provider_payment_id'  => $booking->payment_transaction_id,
                    'status'               => 'paid',
                    'paid_at'              => $booking->updated_at,
                ]);
            }

            if (!$localPayment) {
                throw new PaymentFailedException(
                    'Kein Payment-Record für Booking #'.$booking->id.' gefunden.'
                );
            }

            $refund = $paymentService->refund($localPayment, (float) $booking->total_price);

            $bookingRefundService->createRefund($booking, (float) $booking->total_price, $refund);


            $booking->bookingSlots()
                ->where('status', 'canceled')
                ->get()
                ->each(fn (CourseBookingSlot $bookingSlot) =>
                    $bookingSlotService->refund($bookingSlot)
            );
           
            $courseBookingService
                ->refreshBookingStatus($booking);

            // 🎁 Restore loyalty points if any were redeemed for this booking
            if ($booking->redeemed_points > 0 && !$booking->points_restored && $booking->user && $booking->user->loyaltyAccount) {
                $loyaltyPointService->earn(
                    $booking->user->loyaltyAccount,
                    $booking->redeemed_points,
                    'earn',
                    'sport',
                    $booking,
                    'Rückerstattung von Treuepunkten nach Buchungsstornierung'
                );
                // Mark points as restored to avoid duplicate refunds
                $booking->update(['points_restored' => true]);
            }
            

        } catch (PaymentFailedException $e) {

            // gezielt retrybar
            report($e);

            throw $e; // ⬅️ wichtig für Retry!
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
     // 1️⃣ Logging
        report($exception);

        // 3️⃣ Admin informieren
        Notification::route('mail', env('ADMIN_MAIL', 'aschuster.development@outlook.de'))
            ->notify(new RefundFailedNotification(
                bookingId: $this->bookingId,
                bookingSlotId: 0,
                reason: $exception->getMessage()
            ));
        }catch (Throwable $e) {
            report($e); // niemals weiterwerfen!
        }
        
        
    }
}

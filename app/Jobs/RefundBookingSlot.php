<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Exceptions\PaymentFailedException;
use App\Models\Course\CourseBookingSlot;
use App\Models\Course\CourseBooking;
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


class RefundBookingSlot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $bookingId;
    public int $bookingSlotId;

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
    public function __construct(int $bookingId, int $bookingSlotId)
    {
        $this->bookingId = $bookingId;
        $this->bookingSlotId = $bookingSlotId;
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
        $bookingSlot = CourseBookingSlot::find($this->bookingSlotId);
        
        try {

            $discount=0;
            if($booking->redeemed_points > 0 && !$booking->points_restored){
                $discount= $booking->bookingSlots()->sum('price') - $booking->total_price;
            }

            $refund = $paymentService->refund(
                $booking,
                $bookingSlot->price - $discount // proportionaler Anteil der Punkte auf diesen Slot
            );

            $bookingRefundService->createRefund(
                $booking,
                $bookingSlot->price,
                $refund
            );

            $bookingSlotService->refund($bookingSlot);
           
            $courseBookingService
                ->refreshBookingStatus($booking);

            // 🎁 Restore loyalty points if any were redeemed for this booking (only once!)
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
        $bookingSlot = CourseBookingSlot::find($this->bookingSlotId);
        $bookingSlot->update(['status' => 'refund_failed']);
        #$bookingSlotService->refund_failed($bookingSlot);

        // 3️⃣ Admin informieren
        Notification::route('mail', env('ADMIN_MAIL', 'aschuster.development@outlook.de'))
            ->notify(new RefundFailedNotification(
                bookingId: $this->bookingId,
                bookingSlotId: $this->bookingSlotId,
                reason: $exception->getMessage()
            ));
        }catch (Throwable $e) {
            report($e); // niemals weiterwerfen!
        }
        
        
    }
}

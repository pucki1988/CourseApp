<?php

namespace App\Services\Loyalty;

use App\Models\Course\CourseBooking;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingLoyaltyPointRestorationService
{
    public function __construct(private LoyaltyPointService $loyaltyPointService) {}

    public function restore(CourseBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $lockedBooking = CourseBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->redeemed_points <= 0 || $lockedBooking->points_restored) {
                return;
            }

            $account = $lockedBooking->user->loyaltyAccount;

            if (! $account) {
                throw new RuntimeException("Treuepunkte für Buchung #{$lockedBooking->id} können ohne Treuekonto nicht zurückgebucht werden.");
            }

            $this->loyaltyPointService->earn(
                $account,
                $lockedBooking->redeemed_points,
                'earn',
                'sport',
                $lockedBooking,
                'Rückerstattung von Treuepunkten nach Buchungsstornierung'
            );

            $lockedBooking->update(['points_restored' => true]);
        });
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseBooking;
use App\Actions\Course\CancelCourseAction;
use App\Models\User;
use App\Mail\CourseConfirmedMail;
use Illuminate\Support\Facades\Mail;
use App\Services\Loyalty\LoyaltyPointService;
use App\Models\Loyalty\LoyaltyAccount;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:cancel-course {slot_id} {--reason=}', function ($slot_id) {
    $slot = CourseSlot::find($slot_id);

    if (! $slot) {
        $this->error("CourseSlot mit ID {$slot_id} wurde nicht gefunden.");
        return 1;
    }

    $reason = $this->option('reason');

    // Action ausführen
    app(CancelCourseAction::class)->execute($slot, $reason);

    $this->info("CourseSlot ID {$slot_id} erfolgreich abgesagt.");
    return 0;
})->describe('Cancel a CourseSlot for testing purposes');

Artisan::command('test:course-mail', function () {
    $slot = CourseSlot::find(50);
    $user = User::find(20);

    Mail::to($user->email)->queue(
        new CourseConfirmedMail($slot, $user)
    );

    $this->info('Mail getestet ✅');
});

Artisan::command('create:missing-loyalty-accounts', function () {
    $users = User::doesntHave(relation: 'loyaltyAccount')->get();

    foreach ($users as $user) {
        $account = LoyaltyAccount::create(['type' => 'user']);
        $user->loyalty_account_id = $account->id;
        $user->save();

        $this->info("LoyaltyAccount für User {$user->id} erstellt.");
    }

    $this->info("Fertig!");

});

Artisan::command('migrate:course-payments', function () {
    $createdPayments = 0;
    $createdOrUpdatedRefunds = 0;

    CourseBooking::whereNotNull('payment_status')
        ->orWhereNotNull('payment_transaction_id')
        ->orWhereHas('refunds')
        ->with(['payment', 'refunds'])
        ->each(function (CourseBooking $booking) use (&$createdPayments, &$createdOrUpdatedRefunds) {
            $legacyPaymentStatus = strtolower((string) $booking->payment_status);

            $mappedPaymentStatus = match ($legacyPaymentStatus) {
                'open' => 'open',
                'pending' => 'pending',
                'paid' => 'paid',
                'failed' => 'failed',
                'canceled', 'cancelled' => 'canceled',
                'expired' => 'expired',
                // Legacy-Refund-States gehörten früher zu payment_status
                'refunded', 'partially_refunded', 'refund_processing', 'refund_failed' => 'paid',
                default => $booking->refunds->isNotEmpty() ? 'paid' : 'pending',
            };

            $payment = $booking->payment;
            $currentPaymentStatus = null;

            if (! $payment) {
                $payment = $booking->payment()->create([
                    'amount' => $booking->total_price,
                    'currency' => 'EUR',
                    'provider' => 'mollie',
                    'method' => 'unknown',
                    'source_type' => CourseBooking::class,
                    'source_id' => $booking->id,
                    'provider_payment_id' => $booking->payment_transaction_id,
                    'status' => $mappedPaymentStatus,
                    'paid_at' => $mappedPaymentStatus === 'paid' ? $booking->updated_at : null,
                    'checkout_url' => $booking->checkout_url,
                ]);

                $createdPayments++;
                $this->info("Payment für CourseBooking {$booking->id} erstellt.");
            } else {
                $currentPaymentStatus = (string) $payment->status;

                if (in_array($currentPaymentStatus, ['open', 'pending', 'paid', 'canceled', 'expired', 'failed'], true)) {
                    $currentPaymentStatus = null;
                }
            }

            if ($currentPaymentStatus !== null) {
                $payment->update([
                    'status' => $mappedPaymentStatus,
                    'paid_at' => $mappedPaymentStatus === 'paid' ? ($payment->paid_at ?? $booking->updated_at) : $payment->paid_at,
                ]);
            }

            foreach ($booking->refunds as $legacyRefund) {
                $legacyRefundStatus = strtolower((string) $legacyRefund->status);

                $mappedRefundStatus = match ($legacyRefundStatus) {
                    'queued' => 'queued',
                    'pending' => 'pending',
                    'processing' => 'processing',
                    'completed', 'refunded' => 'refunded',
                    'failed' => 'failed',
                    'canceled', 'cancelled' => 'canceled',
                    default => 'queued',
                };

                $providerRefundId = $legacyRefund->payment_refund_id ?: 'legacy-booking-refund-'.$legacyRefund->id;

                $payment->refunds()->updateOrCreate(
                    ['provider_refund_id' => $providerRefundId],
                    [
                        'amount' => (float) $legacyRefund->amount,
                        'currency' => $payment->currency ?? 'EUR',
                        'status' => $mappedRefundStatus,
                        'completed_at' => $mappedRefundStatus === 'refunded'
                            ? ($legacyRefund->refunded_at ?? $legacyRefund->updated_at)
                            : null,
                        'failed_at' => $mappedRefundStatus === 'failed' ? $legacyRefund->updated_at : null,
                        'canceled_at' => $mappedRefundStatus === 'canceled' ? $legacyRefund->updated_at : null,
                    ]
                );

                $createdOrUpdatedRefunds++;
            }
        });

    $this->info("Migration abgeschlossen. Payments erstellt: {$createdPayments}, Refunds migriert: {$createdOrUpdatedRefunds}");

});


Schedule::command('coaches:generate-billing')
    ->monthlyOn(3, '08:00');
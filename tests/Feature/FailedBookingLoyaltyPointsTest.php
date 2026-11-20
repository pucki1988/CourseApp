<?php

use App\Jobs\RefundFailedBookingWalletPayments;
use App\Models\Course\CourseBooking;
use App\Models\Loyalty\LoyaltyAccount;
use App\Models\User;
use App\Services\Loyalty\BookingLoyaltyPointRestorationService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Support\Facades\Queue;
use Mollie\Api\MollieApiClient;
use Mollie\Laravel\Facades\Mollie;

function failedPaymentBooking(int $points = 100): array
{
    $account = LoyaltyAccount::create(['type' => 'user']);
    $user = User::factory()->create(['loyalty_account_id' => $account->id]);
    $booking = CourseBooking::create([
        'user_id' => $user->id,
        'total_price' => 10,
        'booking_type' => 'per_slot',
        'redeemed_points' => $points,
    ]);
    $account->transactions()->create([
        'points' => -$points,
        'type' => 'redeem',
        'origin' => 'sport',
        'source_type' => CourseBooking::class,
        'source_id' => $booking->id,
        'balance_after' => -$points,
    ]);
    $payment = $booking->payments()->create([
        'amount' => 10,
        'method' => 'ideal',
        'provider' => 'mollie',
        'provider_payment_id' => 'tr_test',
        'status' => 'open',
    ]);

    return [$booking, $account, $payment];
}

test('failed and expired mollie webhooks restore booking points only once', function (string $status) {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();

    $providerPayment = Mockery::mock();
    $providerPayment->metadata = (object) ['payment_id' => $payment->id];
    $providerPayment->method = 'ideal';
    $providerPayment->shouldReceive('hasRefunds')->andReturn(false);
    $providerPayment->shouldReceive('isPaid')->andReturn(false);
    $providerPayment->shouldReceive('isFailed')->andReturn($status === 'failed');
    $providerPayment->shouldReceive('isCanceled')->andReturn(false);
    $providerPayment->shouldReceive('isExpired')->andReturn($status === 'expired');
    $endpoint = Mockery::mock();
    $endpoint->shouldReceive('get')->with('tr_test')->andReturn($providerPayment);
    $api = new MollieApiClient();
    $api->payments = $endpoint;
    Mollie::shouldReceive('api')->andReturn($api);

    $this->postJson('/api/webhooks/mollie', ['id' => 'tr_test'])->assertNoContent();
    $this->postJson('/api/webhooks/mollie', ['id' => 'tr_test'])->assertNoContent();
    app(BookingLoyaltyPointRestorationService::class)->restore($booking);

    expect($payment->fresh()->status)->toBe($status)
        ->and((bool) $booking->fresh()->points_restored)->toBeTrue()
        ->and($account->balance())->toBe(0);
    $this->assertDatabaseCount('loyalty_point_transactions', 2);
    $this->assertDatabaseHas('loyalty_point_transactions', [
        'loyalty_account_id' => $account->id,
        'points' => 100,
        'type' => 'earn',
        'origin' => 'sport',
        'source_type' => CourseBooking::class,
        'source_id' => $booking->id,
    ]);
    Queue::assertPushed(RefundFailedBookingWalletPayments::class, 1);
})->with(['failed', 'expired']);

test('already failed payments can restore points missed by an earlier webhook', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();
    $payment->update(['status' => 'failed']);

    app(PaymentProcessor::class)->handleFailed($payment, 'failed');

    expect((bool) $booking->fresh()->points_restored)->toBeTrue()
        ->and($account->balance())->toBe(0);
    Queue::assertNothingPushed();
});

test('fully paid bookings do not have their points restored for a failed payment', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();
    $booking->payments()->create([
        'amount' => 10,
        'method' => 'wallet',
        'provider' => 'wallet',
        'status' => 'paid',
    ]);

    app(PaymentProcessor::class)->handleFailed($payment, 'failed');

    expect((bool) $booking->fresh()->points_restored)->toBeFalse()
        ->and($account->balance())->toBe(-100);
    Queue::assertNothingPushed();
});

test('payment status is rolled back when restoring points fails', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();
    $loyalty = Mockery::mock(LoyaltyPointService::class);
    $loyalty->shouldReceive('earn')->once()->andThrow(new RuntimeException('restore failed'));
    $this->app->instance(LoyaltyPointService::class, $loyalty);

    expect(fn () => app(PaymentProcessor::class)->handleFailed($payment, 'failed'))
        ->toThrow(RuntimeException::class, 'restore failed');

    expect($payment->fresh()->status)->toBe('open')
        ->and((bool) $booking->fresh()->points_restored)->toBeFalse()
        ->and($account->balance())->toBe(-100);
    Queue::assertNothingPushed();
});

test('a missing loyalty account leaves the payment retryable', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();
    $booking->user->update(['loyalty_account_id' => null]);

    expect(fn () => app(PaymentProcessor::class)->handleFailed($payment, 'expired'))
        ->toThrow(RuntimeException::class, 'ohne Treuekonto');

    expect($payment->fresh()->status)->toBe('open')
        ->and((bool) $booking->fresh()->points_restored)->toBeFalse()
        ->and($account->balance())->toBe(-100);
    Queue::assertNothingPushed();
});

test('failed payments with a partial wallet payment restore points and refund the wallet', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();
    $payment->update(['amount' => 6]);
    $booking->payments()->create([
        'amount' => 4,
        'method' => 'wallet',
        'provider' => 'wallet',
        'status' => 'paid',
    ]);

    app(PaymentProcessor::class)->handleFailed($payment, 'failed');

    expect((bool) $booking->fresh()->points_restored)->toBeTrue()
        ->and($account->balance())->toBe(0);
    Queue::assertPushed(RefundFailedBookingWalletPayments::class, 1);
});

test('points previously restored by a refund are not restored again on a failed payment', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();
    app(BookingLoyaltyPointRestorationService::class)->restore($booking);

    app(PaymentProcessor::class)->handleFailed($payment, 'failed');

    expect($account->balance())->toBe(0)
        ->and($account->transactions()->count())->toBe(2);
});

test('canceled payments retain their existing loyalty behavior', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking();

    app(PaymentProcessor::class)->handleFailed($payment, 'canceled');

    expect($payment->fresh()->status)->toBe('canceled')
        ->and((bool) $booking->fresh()->points_restored)->toBeFalse()
        ->and($account->balance())->toBe(-100);
});

test('payments without redeemed points do not create a restoration', function () {
    Queue::fake();
    [$booking, $account, $payment] = failedPaymentBooking(0);

    app(PaymentProcessor::class)->handleFailed($payment, 'expired');

    expect((bool) $booking->fresh()->points_restored)->toBeFalse()
        ->and($account->transactions()->count())->toBe(1);
});

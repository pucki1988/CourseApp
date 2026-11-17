<?php

use App\Events\CourseBookingCreate;
use App\Jobs\RefundBookingSlot;
use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseSlot;
use App\Models\Loyalty\LoyaltyAccount;
use App\Models\Loyalty\LoyaltyPointTransaction;
use App\Models\Member\Member;
use App\Models\User;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use App\Services\Course\CourseCouponService;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\Payments\BookingPaymentRefundService;
use Binafy\LaravelDiscount\Events\DiscountApplied;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

function couponCourse(string $type = 'per_slot', float $memberDiscount = 0): Course
{
    return Course::create([
        'title' => 'Testkurs',
        'booking_type' => $type,
        'price' => 100,
        'capacity' => 20,
        'member_discount' => $memberDiscount,
    ]);
}

function couponSlot(Course $course, float $price): CourseSlot
{
    return CourseSlot::create([
        'course_id' => $course->id,
        'date' => now()->addMonth()->toDateString(),
        'price' => $price,
        'capacity' => 20,
        'status' => 'active',
    ]);
}

function coupon(array $attributes = []): Discount
{
    return Discount::create(array_merge([
        'name' => 'Testcoupon',
        'code' => 'TEST',
        'type' => 'fixed',
        'value' => 5,
        'currency' => 'EUR',
    ], $attributes));
}

test('a fixed coupon is combined with the member discount once per booking', function () {
    $user = User::factory()->create();
    Member::create([
        'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member',
        'birth_date' => '1990-01-01', 'entry_date' => '2020-01-01', 'external_id' => '123',
        'city' => 'Ort', 'zip_code' => '12345', 'street' => 'Strasse', 'gender' => 'diverse',
    ]);
    $this->actingAs($user);
    $course = couponCourse(memberDiscount: 2);
    $slots = [couponSlot($course, 10), couponSlot($course, 20)];
    $coupon = coupon();
    $booking = app(CourseBookingService::class)->store(new Request([
        'slots' => array_column($slots, 'id'), 'coupon_code' => 'test',
    ]), $course);

    expect($booking->total_price)->toBe('21.00')
        ->and($booking->coupon_discount)->toBe('5.00')
        ->and($booking->bookingSlots->sum('price'))->toBe(21.0)
        ->and($booking->bookingSlots->sum('coupon_refund_price'))->toBe(21.0)
        ->and($coupon->fresh()->used_count)->toBe(1);
    $this->assertDatabaseHas('discount_usages', ['discount_id' => $coupon->id, 'user_id' => $user->id, 'amount' => 5]);
});

test('buy x get y uses the average slot price and distributes cents exactly', function () {
    Event::fake([DiscountApplied::class]);
    $user = User::factory()->create();
    $this->actingAs($user);
    $course = couponCourse();
    $slots = [couponSlot($course, 10), couponSlot($course, 20), couponSlot($course, 30)];
    $coupon = coupon(['type' => 'buy_x_get_y', 'value' => 0, 'conditions' => ['buy' => 2, 'get' => 1]]);
    $booking = app(CourseBookingService::class)->store(new Request([
        'slots' => array_column($slots, 'id'), 'coupon_code' => 'TEST',
    ]), $course);

    expect($booking->total_price)->toBe('40.00')
        ->and($booking->coupon_discount)->toBe('20.00')
        ->and($booking->bookingSlots->pluck('price')->all())->toBe(['6.67', '13.33', '20.00'])
        ->and($booking->bookingSlots->sum('coupon_refund_price'))->toBe(40.0)
        ->and($coupon->fresh()->used_count)->toBe(1);
    $this->assertDatabaseHas('discount_usages', ['discount_id' => $coupon->id, 'user_id' => $user->id, 'amount' => 20]);
    Event::assertDispatched(DiscountApplied::class);
});

test('buy x get y honors the package limits and percentage', function () {
    $user = User::factory()->create();
    coupon([
        'type' => 'buy_x_get_y',
        'value' => 0,
        'conditions' => ['buy' => 2, 'get' => 1, 'get_discount_percentage' => 50, 'max_free_items' => 1],
        'max_discount_amount' => 8,
    ]);

    $result = DB::transaction(fn () => app(CourseCouponService::class)->redeem('TEST', $user, [1000, 2000, 3000, 1000, 2000, 3000]));
    expect($result['discount'])->toBe(800);
});

test('whole course percentage coupons are applied after member discount', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse('per_course');
    couponSlot($course, 0);
    coupon(['type' => 'percentage', 'value' => 25]);
    $booking = app(CourseBookingService::class)->store(new Request(['coupon_code' => 'TEST']), $course);
    expect($booking->total_price)->toBe('75.00')->and($booking->coupon_discount)->toBe('25.00');
});

test('limited, expired and inapplicable coupons fail without creating bookings', function (array $attributes) {
    $this->actingAs(User::factory()->create());
    $course = couponCourse();
    $slot = couponSlot($course, 10);
    $coupon = coupon($attributes);

    expect(fn () => app(CourseBookingService::class)->store(new Request([
        'slots' => [$slot->id], 'coupon_code' => 'TEST',
    ]), $course))->toThrow(ValidationException::class);
    expect(CourseBooking::count())->toBe(0)->and($coupon->fresh()->used_count)->toBe($attributes['used_count'] ?? 0);
})->with([
    'expired' => [['expires_at' => now()->subDay()]],
    'inactive' => [['is_active' => false]],
    'global limit' => [['usage_limit' => 1, 'used_count' => 1]],
    'too few slots' => [['type' => 'buy_x_get_y', 'value' => 0, 'conditions' => ['buy' => 2, 'get' => 1]]],
]);

test('redemptions roll back with a failed booking transaction and respect per-user limits', function () {
    $user = User::factory()->create();
    $discount = coupon(['usage_limit_per_user' => 1]);
    $service = app(CourseCouponService::class);
    try {
        DB::transaction(function () use ($service, $user) {
            $service->redeem('TEST', $user, [1000]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }
    expect($discount->fresh()->used_count)->toBe(0);
    DB::transaction(fn () => $service->redeem('TEST', $user, [1000]));
    expect(fn () => DB::transaction(fn () => $service->redeem('TEST', $user, [1000])))->toThrow(ValidationException::class);
    expect($discount->fresh()->used_count)->toBe(1);
});

test('a fully discounted booking is paid without contacting an external payment provider', function () {
    Event::fake([CourseBookingCreate::class]);
    $user = User::factory()->create();
    $this->actingAs($user);
    $course = couponCourse('per_course');
    couponSlot($course, 0);
    coupon(['type' => 'percentage', 'value' => 100]);

    $response = $this->postJson('/api/booking/'.$course->id, ['coupon_code' => 'TEST']);
    $response->assertOk()->assertJsonPath('booking.total_price', '0.00')
        ->assertJsonPath('booking.status', 'paid')
        ->assertJsonPath('booking.payments.0.provider', 'coupon')
        ->assertJsonPath('booking.payments.0.status', 'paid');
    Event::assertDispatched(CourseBookingCreate::class);
});

test('the booking API rejects invalid coupons and invalid slot input', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse();
    $slot = couponSlot($course, 10);
    $this->postJson('/api/booking/'.$course->id, ['slots' => [$slot->id], 'coupon_code' => 'UNKNOWN'])
        ->assertUnprocessable()->assertJsonPath('message', 'Der Coupon ist ungültig oder für Kursbuchungen nicht verfügbar.');
    $this->postJson('/api/booking/'.$course->id, ['slots' => [$slot->id, $slot->id]])
        ->assertUnprocessable();
    expect(CourseBooking::count())->toBe(0);
});

test('coupon validation previews the booking price without redeeming or applying the coupon', function () {
    Event::fake([DiscountApplied::class]);
    $user = User::factory()->create();
    Member::create([
        'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member',
        'birth_date' => '1990-01-01', 'entry_date' => '2020-01-01', 'external_id' => '123',
        'city' => 'Ort', 'zip_code' => '12345', 'street' => 'Strasse', 'gender' => 'diverse',
    ]);
    $this->actingAs($user);
    $course = couponCourse(memberDiscount: 2);
    $slots = [couponSlot($course, 10), couponSlot($course, 20)];
    $discount = coupon(['usage_limit' => 1]);
    $payload = ['slots' => array_column($slots, 'id'), 'coupon_code' => ' test '];

    foreach ([1, 2] as $_) {
        $this->postJson('/api/booking/'.$course->id.'/coupon/validate', $payload)
            ->assertOk()->assertExactJson([
                'coupon_code' => 'TEST', 'coupon_discount' => '5.00', 'total_price' => '21.00',
            ]);
    }

    expect($discount->fresh()->used_count)->toBe(0)->and(CourseBooking::count())->toBe(0);
    $this->assertDatabaseCount('discount_usages', 0);
    Event::assertNotDispatched(DiscountApplied::class);
    $booking = app(CourseBookingService::class)->store(new Request($payload), $course);
    expect($booking->total_price)->toBe('21.00')->and($booking->coupon_discount)->toBe('5.00');
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
});

test('whole course coupon validation returns the applicable percentage', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse('per_course');
    coupon(['type' => 'percentage', 'value' => 25]);
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', ['coupon_code' => 'TEST'])
        ->assertOk()->assertExactJson([
            'coupon_code' => 'TEST', 'coupon_discount' => '25.00', 'total_price' => '75.00',
        ]);
});

test('buy x get y preview matches the final booking for differently priced slots', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse();
    $slots = [couponSlot($course, 10), couponSlot($course, 20), couponSlot($course, 30)];
    $discount = coupon(['type' => 'buy_x_get_y', 'value' => 0, 'conditions' => ['buy' => 2, 'get' => 1]]);
    $payload = ['slots' => array_column($slots, 'id'), 'coupon_code' => 'TEST'];
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', $payload)
        ->assertOk()->assertExactJson([
            'coupon_code' => 'TEST', 'coupon_discount' => '20.00', 'total_price' => '40.00',
        ]);
    $booking = app(CourseBookingService::class)->store(new Request($payload), $course);
    expect($booking->coupon_discount)->toBe('20.00')->and($booking->total_price)->toBe('40.00')
        ->and($discount->fresh()->used_count)->toBe(1);
});

test('coupon validation rejects expired, inactive and exhausted coupons', function (array $attributes) {
    $this->actingAs(User::factory()->create());
    $course = couponCourse('per_course');
    $discount = coupon($attributes);
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', ['coupon_code' => 'TEST'])
        ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    expect($discount->fresh()->used_count)->toBe($attributes['used_count'] ?? 0);
    $this->assertDatabaseCount('discount_usages', 0);
})->with([
    'expired' => [['expires_at' => now()->subDay()]],
    'inactive' => [['is_active' => false]],
    'global limit' => [['usage_limit' => 1, 'used_count' => 1]],
    'not started' => [['starts_at' => now()->addDay()]],
]);

test('coupon validation respects the current user redemption limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $course = couponCourse('per_course');
    $discount = coupon(['usage_limit_per_user' => 1]);
    DB::transaction(fn () => app(CourseCouponService::class)->redeem('TEST', $user, [10000]));
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', ['coupon_code' => 'TEST'])
        ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    expect($discount->fresh()->used_count)->toBe(1);
    $this->assertDatabaseCount('discount_usages', 1);
});

test('coupon validation rejects invalid codes and unavailable or malformed slot selections', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse();
    $slot = couponSlot($course, 10);
    $foreign = couponSlot(couponCourse(), 10);
    coupon(['type' => 'buy_x_get_y', 'value' => 0, 'conditions' => ['buy' => 2, 'get' => 1]]);
    $url = '/api/booking/'.$course->id.'/coupon/validate';

    $this->postJson($url, ['slots' => [$slot->id], 'coupon_code' => 'UNKNOWN'])
        ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    $this->postJson($url, ['slots' => [$slot->id], 'coupon_code' => 'TEST'])
        ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    $this->postJson($url, ['slots' => [$foreign->id], 'coupon_code' => 'TEST'])
        ->assertUnprocessable()->assertJsonValidationErrors('slots');
    $this->postJson($url, ['slots' => [$slot->id, $slot->id], 'coupon_code' => 'TEST'])
        ->assertUnprocessable()->assertJsonValidationErrors('slots.0');
    $this->postJson($url, ['slots' => [$slot->id], 'coupon_code' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    expect(CourseBooking::count())->toBe(0);
});

test('a coupon is checked again when booking after a successful preview', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse('per_course');
    $discount = coupon();
    $payload = ['coupon_code' => 'TEST'];
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', $payload)->assertOk();
    $discount->update(['is_active' => false]);
    $this->postJson('/api/booking/'.$course->id, $payload)->assertUnprocessable();
    expect(CourseBooking::count())->toBe(0)->and($discount->fresh()->used_count)->toBe(0);
});

test('coupon validation requires authentication', function () {
    $course = couponCourse('per_course');
    $this->postJson('/api/booking/'.$course->id.'/coupon/validate', ['coupon_code' => 'TEST'])
        ->assertUnauthorized();
});

test('coupon and loyalty points leave exact refundable cash shares for all slots', function () {
    config()->set('loyalty.point_value_eur', 0.01);
    $account = LoyaltyAccount::create(['type' => 'user']);
    $account->transactions()->create(['points' => 400, 'type' => 'earn', 'origin' => 'sport', 'balance_after' => 400]);
    $user = User::factory()->create(['loyalty_account_id' => $account->id]);
    $this->actingAs($user);
    $course = couponCourse();
    $slots = [couponSlot($course, 10), couponSlot($course, 20)];
    coupon(['value' => 5]);
    $booking = app(CourseBookingService::class)->store(new Request([
        'slots' => array_column($slots, 'id'), 'coupon_code' => 'TEST', 'use_loyalty' => true,
    ]), $course);

    expect($booking->total_price)->toBe('21.00')
        ->and($booking->redeemed_points)->toBe(400)
        ->and($booking->bookingSlots->pluck('coupon_refund_price')->all())->toBe(['7.00', '14.00']);

    $refunds = Mockery::mock(BookingPaymentRefundService::class);
    $refunds->shouldReceive('refund')->once()->withArgs(fn ($b, $amount) => $b->id === $booking->id && $amount === 7.0);
    $refunds->shouldReceive('refund')->once()->withArgs(fn ($b, $amount) => $b->id === $booking->id && $amount === 14.0);
    $loyalty = Mockery::mock(LoyaltyPointService::class);
    $loyalty->shouldReceive('earn')->once()->andReturn(new LoyaltyPointTransaction);
    foreach ($booking->bookingSlots as $slot) {
        (new RefundBookingSlot($booking->id, $slot->id))->handle(
            $refunds, app(CourseBookingSlotService::class), app(CourseBookingService::class), $loyalty
        );
    }
    expect($booking->fresh()->points_restored)->toBe(1);
});

test('a coupon cannot be used with a slot from another course', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse();
    $foreign = couponSlot(couponCourse(), 10);
    coupon();
    expect(fn () => app(CourseBookingService::class)->store(new Request([
        'slots' => [$foreign->id], 'coupon_code' => 'TEST',
    ]), $course))->toThrow(Exception::class);
    expect(CourseBooking::count())->toBe(0);
});

test('ordinary bookings without a coupon retain their original price', function () {
    $this->actingAs(User::factory()->create());
    $course = couponCourse();
    $slot = couponSlot($course, 12.50);
    $booking = app(CourseBookingService::class)->store(new Request(['slots' => [$slot->id]]), $course);
    expect($booking->total_price)->toBe('12.50')->and($booking->coupon_code)->toBeNull()
        ->and($booking->bookingSlots->first()->coupon_refund_price)->toBeNull();
});

test('coupon management is restricted to users with course management permission', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('course_management.coupons.index'))->assertForbidden();
    Volt::test('course_management.coupons.index')->call('createCoupon')->assertForbidden();
});

test('management can create and deactivate a buy x get y coupon', function () {
    $this->actingAs(User::factory()->create());
    Gate::before(fn ($user, $ability) => $ability === 'courses.manage' ? true : null);
    Volt::test('course_management.coupons.index')
        ->set('name', 'Aktion')->set('code', 'aktion')->set('type', 'buy_x_get_y')
        ->set('buy', '2')->set('get', '1')->call('createCoupon')->assertHasNoErrors();
    $discount = Discount::where('code', 'AKTION')->firstOrFail();
    expect($discount->conditions)->toBe(['buy' => 2, 'get' => 1, 'get_discount_percentage' => 100]);
    Volt::test('course_management.coupons.index')->call('toggleCoupon', $discount->id);
    expect($discount->fresh()->is_active)->toBeFalse();
});

test('buy x get y settings created in management limit the discount per booking', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Gate::before(fn ($user, $ability) => $ability === 'courses.manage' ? true : null);
    Volt::test('course_management.coupons.index')
        ->set('name', 'Aktion')->set('code', 'aktion')->set('type', 'buy_x_get_y')
        ->set('buy', '2')->set('get', '1')->set('getDiscountPercentage', '50')
        ->set('maxFreeItems', '2')->call('createCoupon')->assertHasNoErrors()
        ->assertSee('Maximal rabattierte Termine pro Buchung: 2');

    $discount = Discount::where('code', 'AKTION')->firstOrFail();
    expect($discount->conditions)->toBe([
        'buy' => 2, 'get' => 1, 'get_discount_percentage' => 50, 'max_free_items' => 2,
    ]);
    $result = DB::transaction(fn () => app(CourseCouponService::class)->redeem('AKTION', $user, array_fill(0, 9, 1000)));
    expect($result['discount'])->toBe(1000);
});

test('management validates buy x get y settings and ignores them for other types', function () {
    $this->actingAs(User::factory()->create());
    Gate::before(fn ($user, $ability) => $ability === 'courses.manage' ? true : null);
    Volt::test('course_management.coupons.index')
        ->set('name', 'Aktion')->set('code', 'aktion')->set('type', 'buy_x_get_y')
        ->set('getDiscountPercentage', '101')->set('maxFreeItems', '0')
        ->call('createCoupon')->assertHasErrors(['getDiscountPercentage', 'maxFreeItems'])
        ->set('getDiscountPercentage', '10.123')->set('maxFreeItems', '1.5')
        ->call('createCoupon')->assertHasErrors(['getDiscountPercentage', 'maxFreeItems'])
        ->set('type', 'fixed')->set('value', '5')
        ->call('createCoupon')->assertHasNoErrors();
    expect(Discount::where('code', 'AKTION')->firstOrFail()->conditions)->toBeNull();
});

test('management validates discount values and allows an expiry without a start', function () {
    $this->actingAs(User::factory()->create());
    Gate::before(fn ($user, $ability) => $ability === 'courses.manage' ? true : null);
    Volt::test('course_management.coupons.index')
        ->set('name', 'Prozent')->set('code', 'PERCENT')->set('value', '101')
        ->call('createCoupon')->assertHasErrors(['value'])
        ->set('value', '10.123')->call('createCoupon')->assertHasErrors(['value'])
        ->set('value', '10')->set('expiresAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('createCoupon')->assertHasNoErrors();
    expect(Discount::where('code', 'PERCENT')->exists())->toBeTrue();
});

<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use App\Models\User;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Binafy\LaravelDiscount\Exceptions\DiscountExpiredException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotActiveException;
use Binafy\LaravelDiscount\Exceptions\DiscountUsageLimitReachedException;
use Binafy\LaravelDiscount\Exceptions\DiscountNotFoundException;
use Binafy\LaravelDiscount\Facades\LaravelDiscount;
use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Validation\ValidationException;

class CourseCouponService
{
    public function priceForCourse(Course $course, User $user): int
    {
        return max(0, (int) round(((float) $course->price - ($user->isMember() ? (float) $course->member_discount : 0)) * 100));
    }

    /**
     * @param  iterable<CourseSlot>  $slots
     * @return list<int>
     */
    public function pricesForSlots(Course $course, User $user, iterable $slots): array
    {
        $memberDiscount = $user->isMember() ? (float) $course->member_discount : 0;
        $prices = [];
        foreach ($slots as $slot) {
            $prices[] = max(0, (int) round(((float) $slot->price - $memberDiscount) * 100));
        }

        return $prices;
    }

    /**
     * @param  list<int>  $prices
     * @return array{code: string, discount: int}
     */
    public function preview(string $code, User $user, array $prices): array
    {
        $discount = $this->findDiscount($code);

        return $this->evaluate($discount, $user, $prices, false);
    }

    /**
     * @param  list<int>  $prices  Prices in cents after the member discount.
     * @return array{code: string, discount: int}
     */
    public function redeem(string $code, User $user, array $prices): array
    {
        $discount = $this->findDiscount($code, true);

        return $this->evaluate($discount, $user, $prices, true);
    }

    private function findDiscount(string $code, bool $lock = false): Discount
    {
        $query = Discount::query()->where('code', strtoupper(trim($code)));
        if ($lock) {
            $query->lockForUpdate();
        }
        $discount = $query->first();
        if (! $discount || ! in_array($discount->type, [DiscountType::Fixed, DiscountType::Percentage, DiscountType::BuyXGetY], true)) {
            throw ValidationException::withMessages(['coupon_code' => 'Der Coupon ist ungültig oder für Kursbuchungen nicht verfügbar.']);
        }

        return $discount;
    }

    /**
     * @param  list<int>  $prices
     * @return array{code: string, discount: int}
     */
    private function evaluate(Discount $discount, User $user, array $prices, bool $redeem): array
    {
        $total = array_sum($prices);

        try {
            if ($redeem) {
                $result = LaravelDiscount::apply(
                    $discount,
                    $total / 100,
                    $user,
                    quantity: count($prices),
                    payload: ['currency' => 'EUR'],
                );
                $amount = (int) round($result->discountAmount * 100);
            } else {
                LaravelDiscount::validate($discount, $total / 100, $user, quantity: count($prices), payload: ['currency' => 'EUR']);
                $amount = (int) round(LaravelDiscount::calculate($discount, $total / 100, count($prices)) * 100);
            }

            $amount = min($total, max(0, $amount));
            if ($amount === 0) {
                throw ValidationException::withMessages(['coupon_code' => 'Der Coupon gewährt für diese Buchung keinen Rabatt.']);
            }

            if ($redeem) {
                LaravelDiscount::redeem($discount, $user, $amount / 100);
            }
        } catch (DiscountExpiredException $e) {
            throw ValidationException::withMessages(['coupon_code' => "Der Coupon {$e->getDiscount()->code} ist abgelaufen."]);
        } catch (DiscountNotActiveException $e) {
            throw ValidationException::withMessages(['coupon_code' => "Der Coupon {$e->getDiscount()->code} ist nicht aktiv."]);
        } catch (DiscountUsageLimitReachedException $e) {
            throw ValidationException::withMessages(['coupon_code' => "Der Coupon {$e->getDiscount()->code} hat sein Einlösungslimit erreicht."]);
        } catch (DiscountNotFoundException $e) {
            throw ValidationException::withMessages(['coupon_code' => "Der Coupon {$e->getDiscount()->code} wurde nicht gefunden."]);
        } catch (DiscountException $e) {
            throw ValidationException::withMessages(['coupon_code' => 'Der Coupon ist ungültig, abgelaufen oder sein Einlösungslimit ist erreicht.']);
        }
        return ['code' => $discount->code, 'discount' => $amount];
    }

    /**
     * Distribute cents proportionally, preserving the exact total.
     *
     * @param  list<int>  $prices
     * @return list<int>
     */
    public function allocate(int $amount, array $prices): array
    {
        $total = array_sum($prices);
        if ($total === 0) {
            return array_fill(0, count($prices), 0);
        }

        $shares = [];
        $remainders = [];
        foreach ($prices as $index => $price) {
            $product = $amount * $price;
            $shares[$index] = intdiv($product, $total);
            $remainders[$index] = $product % $total;
        }
        arsort($remainders);
        $remaining = $amount - array_sum($shares);
        foreach (array_keys($remainders) as $index) {
            if ($remaining-- <= 0) {
                break;
            }
            $shares[$index]++;
        }
        ksort($shares);

        return array_values($shares);
    }
}

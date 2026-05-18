<?php

namespace App\Actions\CourseBooking;


use App\Models\Course\CourseBookingSlot;
use App\Models\Course\Course;
use App\Models\Accounting\Account;
use Bavix\Wallet\Exceptions\BalanceIsEmpty;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Course\CourseBookingService;
use App\Contracts\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;
use RuntimeException;

class CreateBookingAction
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected PaymentService $paymentService,
    ) {}
    public function execute(Request $request, Course $course): array
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', Rule::in(['provider', 'wallet_sports_voucher'])],
        ]);

        $useSportsWallet = ($validated['payment_method'] ?? null) === 'wallet_sports_voucher';

        return DB::transaction(function () use ($request, $course, $useSportsWallet) {

            $newBooking = $this->courseBookingService->store($request, $course);

            if ($newBooking->payment()->exists()) {
                throw new LogicException('Für diese Buchung existiert bereits ein Payment.');
            }

            $amountInCents = (int) round(((float) $newBooking->total_price) * 100);

            if ($useSportsWallet) {
                $user = $request->user();
                $account = $user?->account;

                if (! $account) {
                    throw new RuntimeException('Keine Zahlung ueber Sports Wallet moeglich: User hat keinen Account.');
                }

                $wallet = $account->walletBySlug(Account::WALLET_SPORTS_VOUCHER);

                if ($amountInCents > 0) {
                    try {
                        $wallet->withdraw($amountInCents, [
                            'type' => 'course_booking_payment',
                            'booking_id' => $newBooking->id,
                            'course_id' => $course->id,
                            'wallet_slug' => Account::WALLET_SPORTS_VOUCHER,
                        ]);
                    } catch (BalanceIsEmpty|InsufficientFunds $exception) {
                        throw new RuntimeException('Nicht genug Guthaben in der Sports Wallet.');
                    }
                }

                $newBooking->payment()->create([
                    'amount' => $newBooking->total_price,
                    'currency' => 'EUR',
                    'method' => 'sports_wallet',
                    'provider' => 'wallet',
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                    'meta' => [
                        'wallet_slug' => Account::WALLET_SPORTS_VOUCHER,
                        'wallet_amount_cents' => $amountInCents,
                    ],
                ]);

                $newBooking->load('payment');
                $this->courseBookingService->refreshBookingStatus($newBooking);

                $data['booking'] = $newBooking->refresh();

                return $data;
            }

            #Lokalen Payment-Record anlegen, dann an Provider übergeben
            
            $localPayment = $newBooking->payment()->create([
                'amount'   => $newBooking->total_price,
                'currency' => 'EUR',
                'method'   => 'pending',
                'provider' => 'mollie',
                'status'   => 'pending',
            ]);

            $newBooking->load('payment');

            $this->paymentService->createPayment($localPayment);
            $this->courseBookingService->refreshBookingStatus($newBooking);

            $data["booking"]=$newBooking->refresh();
            return $data;
        });
    }
}

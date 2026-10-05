<?php

namespace App\Actions\CourseBooking;


use App\Models\Accounting\Account;
use App\Models\Course\Course;
use Illuminate\Support\Facades\DB;
use App\Services\Course\CourseBookingService;
use App\Services\Payments\PaymentServiceResolver;
use App\Exceptions\WalletInsufficientFundsException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class CreateBookingAction
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected PaymentServiceResolver $paymentServiceResolver,
    ) {}
    public function execute(Request $request, Course $course): array
    {
        $validated = $request->validate([
            'pay_provider' => ['nullable', Rule::in(['mollie', 'wallet'])],
            'wallet_slug' => ['nullable', 'string', Rule::in(array_keys(Account::systemWalletDefinitions()))],
        ]);

        $useSportsWallet = ($validated['pay_provider'] ?? null) === 'wallet';
        $walletSlug = null;

        if ($useSportsWallet) {
            $allowedWallets = collect($course->allowed_wallet_slugs ?? [])
                ->filter(fn ($slug) => is_string($slug) && $slug !== '')
                ->values();

            if ($allowedWallets->isEmpty()) {
                throw new LogicException('Für diesen Kurs ist keine Wallet-Zahlung konfiguriert.');
            }

            $requestedWalletSlug = $validated['wallet_slug'] ?? null;

            if (is_string($requestedWalletSlug) && $requestedWalletSlug !== '') {
                if (! $allowedWallets->contains($requestedWalletSlug)) {
                    throw new LogicException('Diese Wallet ist für den Kurs nicht zugelassen.');
                }

                $walletSlug = $requestedWalletSlug;
            } elseif ($allowedWallets->count() === 1) {
                $walletSlug = (string) $allowedWallets->first();
            } else {
                throw new LogicException('Bitte wähle eine erlaubte Wallet für diesen Kurs aus.');
            }
        }

        return DB::transaction(function () use ($request, $course, $useSportsWallet, $walletSlug) {

            $newBooking = $this->courseBookingService->store($request, $course);

            if ($newBooking->payments()->exists()) {
                throw new LogicException('Für diese Buchung existieren bereits Payments.');
            }

            $totalCents = (int) round(((float) $newBooking->total_price) * 100);
            $walletCents = 0;
            $account = $request->user()?->account;

            if ($useSportsWallet && ! $account) {
                throw new LogicException('Für eine Wallet-Zahlung ist ein Account erforderlich.');
            }

            if ($useSportsWallet) {
                $wallet = $account->walletBySlug($walletSlug);
                $walletCents = min($totalCents, max(0, (int) $wallet->balanceInt));
            }

            if ($walletCents > 0) {
                $walletPayment = $newBooking->payments()->create([
                    'amount' => number_format($walletCents / 100, 2, '.', ''),
                    'currency' => 'EUR',
                    'method' => 'wallet',
                    'provider' => 'wallet',
                    'status' => 'pending',
                    'meta' => [
                        'wallet_slug' => $walletSlug,
                        'payer_account_id' => $account->id,
                    ],
                ]);

                try {
                    $this->paymentServiceResolver
                        ->resolve($walletPayment)
                        ->createPayment($walletPayment);
                } catch (WalletInsufficientFundsException) {
                    $walletPayment->delete();
                    $walletCents = 0;
                }
            }

            $providerCents = $totalCents - $walletCents;
            if ($providerCents > 0 || $totalCents === 0) {
                $providerPayment = $newBooking->payments()->create([
                    'amount' => number_format($providerCents / 100, 2, '.', ''),
                    'currency' => 'EUR',
                    'method' => 'pending',
                    'provider' => 'mollie',
                    'status' => 'pending',
                ]);

                $this->paymentServiceResolver
                    ->resolve($providerPayment)
                    ->createPayment($providerPayment);
            }

            $data['booking'] = $newBooking->refresh()->load('payments');
            return $data;
        });
    }
}

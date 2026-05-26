<?php

namespace App\Actions\CourseBooking;


use App\Models\Accounting\Account;
use App\Models\Course\Course;
use Illuminate\Support\Facades\DB;
use App\Services\Course\CourseBookingService;
use App\Services\Payments\PaymentServiceResolver;
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

            if ($newBooking->payment()->exists()) {
                throw new LogicException('Für diese Buchung existiert bereits ein Payment.');
            }

            $localPayment = $newBooking->payment()->create([
                'amount'   => $newBooking->total_price,
                'currency' => 'EUR',
                'method'   => $useSportsWallet ? 'wallet' : 'pending',
                'provider' => $useSportsWallet ? 'wallet' : 'mollie',
                'status'   => 'pending',
                'meta'     => $useSportsWallet
                    ? [
                        'wallet_slug' => $walletSlug,
                        'payer_account_id' => $request->user()?->account?->id,
                    ]
                    : null,
            ]);

            $newBooking->load('payment');

            $this->paymentServiceResolver
                ->resolve($localPayment)
                ->createPayment($localPayment);

            $this->courseBookingService->refreshBookingStatus($newBooking);

            $data["booking"]=$newBooking->refresh();
            return $data;
        });
    }
}

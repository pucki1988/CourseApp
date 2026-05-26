<?php

namespace App\Services\Payments;

use App\Contracts\PaymentService;
use App\Data\Payments\PaymentResult;
use App\Data\Payments\RefundResult;
use App\Exceptions\PaymentFailedException;
use App\Models\Accounting\Account;
use App\Models\Course\CourseBooking;
use App\Models\Payment\Payment;
use App\Models\Shop\Order;
use Bavix\Wallet\Exceptions\BalanceIsEmpty;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletPaymentService implements PaymentService
{
    public function __construct(
        protected PaymentProcessor $paymentProcessor,
    ) {}

    public function createPayment(Payment $payment): PaymentResult
    {
        if ($payment->isPaid()) {
            return new PaymentResult(
                provider: 'wallet',
                transactionId: $payment->provider_payment_id ?? ('wallet-payment-'.$payment->id),
                checkoutUrl: null,
                status: 'paid',
                method: $payment->method,
            );
        }

        $account = $this->resolvePayerAccount($payment);
        if (! $account) {
            throw new PaymentFailedException('Keine Wallet-Zahlung möglich: kein Account gefunden.');
        }

        $walletSlug = $this->resolveWalletSlug($payment);
        $amountInCents = (int) round(((float) $payment->amount) * 100);

        try {
            DB::transaction(function () use ($payment, $account, $walletSlug, $amountInCents): void {
                if ($amountInCents > 0) {
                    $wallet = $account->walletBySlug($walletSlug);

                    $wallet->withdraw($amountInCents, [
                        'type' => 'wallet_payment',
                        'payment_id' => $payment->id,
                        'source_type' => $payment->source_type,
                        'source_id' => $payment->source_id,
                        'wallet_slug' => $walletSlug,
                    ]);
                }

                $payment->update([
                    'provider' => 'wallet',
                    'provider_payment_id' => $payment->provider_payment_id ?? ('wallet-payment-'.$payment->id),
                    'method' => $payment->method === 'pending' ? 'wallet' : $payment->method,
                    'meta' => array_merge($payment->meta ?? [], [
                        'wallet_slug' => $walletSlug,
                        'wallet_amount_cents' => $amountInCents,
                        'payer_account_id' => $account->id,
                    ]),
                ]);

                // Reuse the same paid side-effects as provider webhooks.
                $this->paymentProcessor->handlePaid($payment->fresh());
            });
        } catch (BalanceIsEmpty|InsufficientFunds $exception) {
            throw new PaymentFailedException('Nicht genug Guthaben in der Wallet.');
        }

        $freshPayment = $payment->fresh();

        return new PaymentResult(
            provider: 'wallet',
            transactionId: $freshPayment->provider_payment_id ?? ('wallet-payment-'.$payment->id),
            checkoutUrl: null,
            status: $freshPayment->status,
            method: $freshPayment->method,
        );
    }

    public function refund(Payment $payment, float $amount): RefundResult
    {
        if (($payment->provider ?? null) !== 'wallet') {
            throw new PaymentFailedException('Wallet-Refund ist nur für Payments mit provider "wallet" möglich.');
        }

        $account = $this->resolvePayerAccount($payment);
        if (! $account) {
            throw new PaymentFailedException('Keine Wallet-Rückerstattung möglich: kein Account gefunden.');
        }

        $walletSlug = $this->resolveWalletSlug($payment);
        $amountInCents = (int) round($amount * 100);

        DB::transaction(function () use ($account, $walletSlug, $payment, $amountInCents): void {
            if ($amountInCents > 0) {
                $wallet = $account->walletBySlug($walletSlug);

                $wallet->deposit($amountInCents, [
                    'type' => 'wallet_refund',
                    'payment_id' => $payment->id,
                    'source_type' => $payment->source_type,
                    'source_id' => $payment->source_id,
                    'wallet_slug' => $walletSlug,
                ]);
            }
        });

        return new RefundResult(
            refundId: 'wallet-refund-'.$payment->id.'-'.Str::uuid()->toString(),
            status: 'refunded',
        );
    }

    public function handleWebhook(string $providerPaymentId): void
    {
        // No-op: Wallet-Zahlungen haben keinen externen Webhook-Provider.
    }

    private function resolvePayerAccount(Payment $payment): ?Account
    {
        $paymentMeta = $payment->meta ?? [];
        $payerAccountId = $paymentMeta['payer_account_id'] ?? null;

        if (is_int($payerAccountId) || ctype_digit((string) $payerAccountId)) {
            return Account::find((int) $payerAccountId);
        }

        $source = $payment->source;

        if ($source instanceof Order) {
            return $source->account ?? $source->user?->account;
        }

        if ($source instanceof CourseBooking) {
            return $source->user?->account;
        }

        return null;
    }

    private function resolveWalletSlug(Payment $payment): string
    {
        $paymentMeta = $payment->meta ?? [];

        return is_string($paymentMeta['wallet_slug'] ?? null)
            ? $paymentMeta['wallet_slug']
            : Account::WALLET_SPORTS_VOUCHER;
    }
}

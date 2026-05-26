<?php

namespace App\Services\Payments;

use App\Contracts\PaymentService;
use App\Models\Payment\Payment;
use InvalidArgumentException;

class PaymentServiceResolver
{
    public function __construct(
        protected MolliePaymentService $molliePaymentService,
        protected WalletPaymentService $walletPaymentService,
    ) {}

    public function resolve(Payment $payment): PaymentService
    {
        return $this->resolveByProvider($payment->provider ?? null);
    }

    public function resolveByProvider(?string $provider): PaymentService
    {
        return match ($provider) {
            'wallet' => $this->walletPaymentService,
            'mollie', null, '' => $this->molliePaymentService,
            default => throw new InvalidArgumentException('Unbekannter Payment-Provider: '.$provider),
        };
    }
}

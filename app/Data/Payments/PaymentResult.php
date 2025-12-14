<?php

namespace App\Data\Payments;

class PaymentResult
{
    public function __construct(
        public string $provider,
        public string $transactionId,
        public ?string $checkoutUrl,
        public string $status
    ) {}
}
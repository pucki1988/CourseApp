<?php

namespace App\Data\Payments;

class RefundResult
{
    public function __construct(
        public string $refundId,
        public string $status
    ) {}
}
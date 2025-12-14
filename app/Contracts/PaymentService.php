<?php

namespace App\Contracts;

use App\Models\Course\CourseBooking;
use App\Data\Payments\PaymentResult;

interface PaymentService
{
    public function createPayment(CourseBooking $booking): PaymentResult;

    public function refund(
        CourseBooking $booking,
        float $amount
    ): RefundResult;

    public function handleWebhook(string $paymentId): void;
}
<?php

namespace App\Contracts;

use App\Models\Payment\Payment;
use App\Data\Payments\PaymentResult;
use App\Data\Payments\RefundResult;

interface PaymentService
{
    /**
     * Initiiert eine Zahlung beim Provider und gibt einen PaymentResult zurück.
     * Erwartet eine bereits persistierte lokale Payment-Zeile.
     */
    public function createPayment(Payment $payment): PaymentResult;

    /**
     * Erstattet einen Betrag über den Provider zurück.
     */
    public function refund(Payment $payment, float $amount): RefundResult;

    public function handleWebhook(string $providerPaymentId): void;
}
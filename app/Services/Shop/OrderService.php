<?php

namespace App\Services\Shop;

use App\Contracts\PaymentService;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    public function createProductCheckout(Product $product, int $quantity, array $customerData, ?User $user = null): Order
    {
        if (! $product->isActive()) {
            throw new InvalidArgumentException('Dieses Produkt ist aktuell nicht verfuegbar.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Die Menge muss mindestens 1 sein.');
        }

        $customerEmail = $customerData['customer_email'] ?? $user?->email;

        if (! is_string($customerEmail) || trim($customerEmail) === '') {
            throw new InvalidArgumentException('Eine gueltige E-Mail-Adresse ist erforderlich.');
        }

        return DB::transaction(function () use ($product, $quantity, $customerData, $user, $customerEmail): Order {
            $unitPrice = (float) $product->price;
            $totalPrice = round($unitPrice * $quantity, 2);

            $order = Order::create([
                'account_id' => $user?->account_id,
                'user_id' => $user?->id,
                'status' => 'pending_payment',
                'customer_name' => $customerData['customer_name'] ?? $user?->name,
                'customer_email' => $customerEmail,
                'currency' => $product->currency,
                'subtotal_amount' => $totalPrice,
                'total_amount' => $totalPrice,
                'meta' => [
                    'origin' => 'shop',
                ],
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_type' => $product->type,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'meta' => [
                    'product_sku' => $product->sku,
                ],
            ]);

            $payment = $order->payments()->create([
                'amount' => $totalPrice,
                'currency' => $product->currency,
                'method' => 'pending',
                'provider' => 'mollie',
                'status' => 'pending',
                'reference' => 'ORDER-'.$order->id,
                'meta' => [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ],
            ]);

            $this->paymentService->createPayment($payment);

            return $order->fresh(['items', 'payments']);
        });
    }
}
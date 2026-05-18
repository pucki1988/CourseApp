<?php

namespace Database\Seeders;

use App\Models\Shop\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'sku' => 'VOUCHER-10',
                'type' => 'voucher_wallet_topup',
                'name' => 'Gutschein 10 EUR',
                'description' => 'Wallet-Gutschein im Wert von 10 EUR.',
                'price' => 10.00,
                'currency' => 'EUR',
                'is_active' => true,
                'meta' => [
                    'wallet_amount_cents' => 1000,
                ],
            ],
            [
                'sku' => 'VOUCHER-25',
                'type' => 'voucher_wallet_topup',
                'name' => 'Gutschein 25 EUR',
                'description' => 'Wallet-Gutschein im Wert von 25 EUR.',
                'price' => 25.00,
                'currency' => 'EUR',
                'is_active' => true,
                'meta' => [
                    'wallet_amount_cents' => 2500,
                ],
            ],
            [
                'sku' => 'VOUCHER-50',
                'type' => 'voucher_wallet_topup',
                'name' => 'Gutschein 50 EUR',
                'description' => 'Wallet-Gutschein im Wert von 50 EUR.',
                'price' => 50.00,
                'currency' => 'EUR',
                'is_active' => true,
                'meta' => [
                    'wallet_amount_cents' => 5000,
                ],
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }
    }
}
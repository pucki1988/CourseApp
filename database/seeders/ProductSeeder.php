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
                'sku' => 'SPORT-VOUCHER-10',
                'type' => 'voucher_wallet_sports',
                'name' => 'Gutschein 10 EUR',
                'description' => 'Der Gutschein ist nur für Sportkurse gültig.',
                'price' => 10.00,
                'currency' => 'EUR',
                'is_active' => true,
                'meta' => [
                    'wallet_amount_cents' => 1000,
                ],
            ],
            [
                'sku' => 'SPORT-VOUCHER-25',
                'type' => 'voucher_wallet_sports',
                'name' => 'Gutschein 25 EUR',
                'description' => 'Der Gutschein ist nur für Sportkurse gültig.',
                'price' => 25.00,
                'currency' => 'EUR',
                'is_active' => true,
                'meta' => [
                    'wallet_amount_cents' => 2500,
                ],
            ],
            [
                'sku' => 'SPORT-VOUCHER-50',
                'type' => 'voucher_wallet_sports',
                'name' => 'Gutschein 50 EUR',
                'description' => 'Der Gutschein ist nur für Sportkurse gültig.',
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
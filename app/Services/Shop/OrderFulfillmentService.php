<?php

namespace App\Services\Shop;

use App\Mail\OrderVouchersMail;
use App\Models\Accounting\Account;
use App\Models\Shop\Order;
use App\Services\Wallet\VoucherWalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class OrderFulfillmentService
{
    public function __construct(
        protected VoucherWalletService $voucherWalletService,
    ) {}

    public function fulfillPaidOrder(Order $order): void
    {
        $order->loadMissing('items');
        $orderMeta = $order->meta ?? [];
        $hasVoucherProducts = false;

        foreach ($order->items as $item) {
            if ($item->product_type !== 'voucher_wallet_topup') {
                continue;
            }

            $hasVoucherProducts = true;

            $meta = $item->meta ?? [];

            if (! empty($meta['vouchers'])) {
                continue;
            }

            $vouchers = [];
            $issuer = $this->resolveIssuerAccount();

            for ($index = 0; $index < $item->quantity; $index++) {
                $voucher = $this->voucherWalletService->issueVoucher(
                    $issuer,
                    (int) round(((float) $item->unit_price) * 100),
                    null,
                    [
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_type' => $item->product_type,
                        'customer_email' => $order->customer_email,
                    ]
                );

                $vouchers[] = [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'amount' => $voucher->data->get('amount'),
                    'currency' => $voucher->data->get('currency', 'EUR'),
                ];
            }

            $item->update([
                'meta' => array_merge($meta, [
                    'vouchers' => $vouchers,
                    'fulfilled_at' => Carbon::now()->toIso8601String(),
                ]),
            ]);
        }

        if (! $hasVoucherProducts) {
            return;
        }

        if (! empty($orderMeta['voucher_mail_sent_at'])) {
            return;
        }

        $order->refresh()->load('items');

        $vouchersForMail = $this->collectVouchersForMail($order);

        if ($vouchersForMail === []) {
            return;
        }

        Mail::to($order->customer_email)->send(new OrderVouchersMail($order, $vouchersForMail));

        $order->update([
            'meta' => array_merge($orderMeta, [
                'voucher_mail_sent_at' => Carbon::now()->toIso8601String(),
                'voucher_mail_to' => $order->customer_email,
            ]),
        ]);
    }

    private function collectVouchersForMail(Order $order): array
    {
        $payload = [];

        foreach ($order->items as $item) {
            $meta = $item->meta ?? [];

            foreach (($meta['vouchers'] ?? []) as $voucher) {
                $payload[] = [
                    'code' => $voucher['code'] ?? '',
                    'amount' => (int) ($voucher['amount'] ?? 0),
                    'currency' => $voucher['currency'] ?? 'EUR',
                    'product_name' => $item->product_name,
                ];
            }
        }

        return array_values(array_filter($payload, fn (array $voucher) => $voucher['code'] !== ''));
    }

    private function resolveIssuerAccount(): Account
    {
        return Account::query()
            ->whereDoesntHave('users')
            ->whereDoesntHave('members')
            ->first() ?? Account::create();
    }
}
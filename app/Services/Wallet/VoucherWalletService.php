<?php

namespace App\Services\Wallet;

use App\Models\Accounting\Account;
use BeyondCode\Vouchers\Facades\Vouchers;
use BeyondCode\Vouchers\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class VoucherWalletService
{
    public function issueVoucher(Account $issuer, int $amountInCents, ?Carbon $expiresAt = null, array $meta = []): Voucher
    {
        if ($amountInCents <= 0) {
            throw new InvalidArgumentException('Voucher amount must be greater than zero.');
        }

        return $issuer->createVoucher(array_merge([
            'amount' => $amountInCents,
            'currency' => 'EUR',
        ], $meta), $expiresAt);
    }

    public function redeemVoucher(Account $account, string $code): Voucher
    {
        return DB::transaction(function () use ($account, $code): Voucher {
            $voucher = Vouchers::check($code);

            if ($voucher->users()->whereKey($account->getKey())->exists()) {
                throw new RuntimeException('Dieser Gutschein wurde bereits von diesem Account eingelöst.');
            }

            // Gutschein ist absichtlich genau einmal global einlösbar.
            if ($voucher->users()->exists()) {
                throw new RuntimeException('Dieser Gutschein wurde bereits eingelöst.');
            }

            $amountInCents = (int) ($voucher->data->get('amount', 0));

            if ($amountInCents <= 0) {
                throw new RuntimeException('Ungültiger Gutscheinbetrag.');
            }

            $account->redeemedVouchers()->attach($voucher->getKey(), [
                'redeemed_at' => now(),
            ]);

            $account->deposit($amountInCents, [
                'type' => 'voucher_redemption',
                'voucher_code' => $voucher->code,
            ]);

            return $voucher;
        });
    }
}
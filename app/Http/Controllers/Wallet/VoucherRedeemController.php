<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Services\Wallet\VoucherWalletService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VoucherRedeemController extends Controller
{
    public function __construct(
        protected VoucherWalletService $voucherWalletService,
    ) {}

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $account = $user?->account;

        if (! $account) {
            return response()->json([
                'message' => 'Kein User-Account zum Einloesen vorhanden.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $voucher = $this->voucherWalletService->redeemVoucher($account, (string) $validated['code']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $wallet = $account->walletBySlug(Account::WALLET_SPORTS_VOUCHER);

        return response()->json([
            'message' => 'Gutschein erfolgreich eingeloest.',
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
            ],
            'wallet' => [
                'slug' => Account::WALLET_SPORTS_VOUCHER,
                'balance' => $wallet->balanceInt,
            ],
        ]);
    }
}

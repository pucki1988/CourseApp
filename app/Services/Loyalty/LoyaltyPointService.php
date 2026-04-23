<?php

namespace App\Services\Loyalty;

use App\Models\Loyalty\LoyaltyPointTransaction;
use App\Models\Loyalty\LoyaltyAccount;
use App\Services\User\AppleWalletPassService;
use App\Services\User\GoogleWalletPassService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoyaltyPointService
{
    public function __construct(
        private GoogleWalletPassService $googleWalletPassService,
        private AppleWalletPassService $appleWalletPassService,
    ) {
    }

    public function earn(LoyaltyAccount $account, int $points, string $type ='earn', string $origin = 'sport', ?Model $source = null, ?string $description = null): LoyaltyPointTransaction
    {
        return $this->record($account, abs($points), $type, $origin, $source, $description);
    }

    public function redeem(LoyaltyAccount $account, int $points, string $type = 'redeem', string $origin = 'sport', ?Model $source = null, ?string $description = null): LoyaltyPointTransaction
    {
        if ($account->balance() < $points) {
            throw new \Exception('Nicht genug Punkte');
        }

        return $this->record($account, -abs($points), $type, $origin,$source, $description);
    }

    protected function record(LoyaltyAccount $account, int $points, string $type,string $origin, ?Model $source, ?string $description): LoyaltyPointTransaction
    {
        return DB::transaction(function () use ($origin, $account, $points, $type, $source, $description) {
            $lockedAccount = LoyaltyAccount::whereKey($account->id)->lockForUpdate()->first();

            $transaction = LoyaltyPointTransaction::create([
                'loyalty_account_id' => $lockedAccount->id,
                'points' => $points,
                'type' => $type,
                'origin' => $origin, 
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'description' => $description,
                'balance_after' => 0,
            ]);

            $balance = LoyaltyPointTransaction::where('loyalty_account_id', $lockedAccount->id)->sum('points');

            #$lockedUser->loyalty_points = $balance;
            #$lockedUser->save();
            $transaction->update(['balance_after' => $balance]);

            $this->syncWalletLoyaltyPointsAfterCommit($lockedAccount, $origin);

            return $transaction;
        });
    }

    private function syncWalletLoyaltyPointsAfterCommit(LoyaltyAccount $account, string $origin): void
    {
        if ($origin !== 'sport') {
            return;
        }

        $user = $account->user;

        if (!$user) {
            return;
        }

        DB::afterCommit(function () use ($user) {
            try {
                $this->googleWalletPassService->updateLoyaltyPoints($user);
            } catch (Throwable $exception) {
                Log::warning('Google Wallet Treuepunkte konnten nach Loyalty-Update nicht synchronisiert werden.', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            try {
                $this->appleWalletPassService->markPassUpdatedForUser($user);
            } catch (Throwable $exception) {
                Log::warning('Apple Wallet Pass konnte nach Loyalty-Update nicht als aktualisiert markiert werden.', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }
}

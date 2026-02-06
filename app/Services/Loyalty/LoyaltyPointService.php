<?php

namespace App\Services\Loyalty;

use App\Models\Loyalty\LoyaltyPointTransaction;
use App\Models\Loyalty\LoyaltyAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoyaltyPointService
{
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

            return $transaction;
        });
    }
}

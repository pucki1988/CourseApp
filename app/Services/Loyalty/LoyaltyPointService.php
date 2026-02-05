<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoyaltyPointService
{
    public function earn(User $user, int $points, string $type, ?Model $source = null, ?string $description = null): LoyaltyPointTransaction
    {
        return $this->record($user, abs($points), $type, $source, $description);
    }

    public function redeem(User $user, int $points, string $type = 'redeem', ?Model $source = null, ?string $description = null): LoyaltyPointTransaction
    {
        return $this->record($user, -abs($points), $type, $source, $description);
    }

    public function recalculate(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();

            $balance = LoyaltyPointTransaction::where('user_id', $lockedUser->id)->sum('points');
            $lockedUser->loyalty_points = $balance;
            $lockedUser->save();

            return $balance;
        });
    }

    protected function record(User $user, int $points, string $type, ?Model $source, ?string $description): LoyaltyPointTransaction
    {
        return DB::transaction(function () use ($user, $points, $type, $source, $description) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();

            $transaction = LoyaltyPointTransaction::create([
                'user_id' => $lockedUser->id,
                'points' => $points,
                'type' => $type,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'description' => $description,
                'balance_after' => 0,
            ]);

            $balance = LoyaltyPointTransaction::where('user_id', $lockedUser->id)->sum('points');

            $lockedUser->loyalty_points = $balance;
            $lockedUser->save();
            $transaction->update(['balance_after' => $balance]);

            return $transaction;
        });
    }
}

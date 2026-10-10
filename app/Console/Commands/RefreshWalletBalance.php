<?php

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use Illuminate\Console\Command;

class RefreshWalletBalance extends Command
{
    protected $signature = 'wallet:refresh-balance {accountId} {walletSlug}';
    protected $description = 'Recalculates a wallet balance from its transactions';
    public function handle(): int
    {
        $account = Account::findOrFail($this->argument('accountId'));
        $wallet = $account->walletBySlug($this->argument('walletSlug'));
        $this->info('Balance before: ' . $wallet->balanceInt);
        $this->info('Transaction sum: ' . $wallet->walletTransactions()->where('confirmed', true)->sum('amount'));
        if (! $this->confirm('Wallet balance neu berechnen?')) {
            return self::SUCCESS;
        }
        $wallet->refreshBalance();
        $wallet->refresh();
        $this->info('Balance after: ' . $wallet->balanceInt);
        return self::SUCCESS;
    }
}

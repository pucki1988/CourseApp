<?php

namespace App\Console\Commands;

use App\Services\Member\MembershipPaymentService;
use Illuminate\Console\Command;

class GenerateMembershipPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:generate-payments 
                            {--months=1 : Number of months ahead to generate payments for}
                            {--dry-run : Show what would be generated without creating payments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring membership payments for all active memberships';

    protected MembershipPaymentService $paymentService;

    /**
     * Create a new command instance.
     */
    public function __construct(MembershipPaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monthsAhead = (int) $this->option('months');
        $dryRun = $this->option('dry-run');

        $this->info("Generating membership payments for {$monthsAhead} month(s) ahead...");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No payments will be created");
        }

        // Generate payments
        $payments = $this->paymentService->generateRecurringPayments(null, $monthsAhead, $dryRun);

        if ($payments->isEmpty()) {
            $this->info("No payments need to be generated.");
            return Command::SUCCESS;
        }

        // Group by billing interval for reporting
        $grouped = $payments->groupBy(function ($payment) {
            return $payment->membership->billing_interval;
        });

        $this->newLine();
        $this->info("Generated {$payments->count()} payment(s):");
        $this->newLine();

        foreach ($grouped as $interval => $intervalPayments) {
            $intervalLabel = match($interval) {
                'monthly' => 'Monatlich',
                'quarterly' => 'Vierteljährlich',
                'semi_annual' => 'Halbjährlich',
                'annual' => 'Jährlich',
                default => 'Unbekannt',
            };
            
            $this->line("  <fg=cyan>{$intervalLabel}</>: {$intervalPayments->count()} payment(s)");
        }

        $this->newLine();
        $totalAmount = $payments->sum('amount');
        $this->info("Total amount: € " . number_format($totalAmount, 2, ',', '.'));

        // Check for memberships without bank account
        $withoutBankAccount = $payments->whereNull('bank_account_id');
        if ($withoutBankAccount->isNotEmpty()) {
            $this->newLine();
            $this->warn("Warning: {$withoutBankAccount->count()} payment(s) without bank account!");
            $this->line("These payments were created but need bank account information.");
        }

        return Command::SUCCESS;
    }
}

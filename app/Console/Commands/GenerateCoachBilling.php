<?php

namespace App\Console\Commands;

use App\Mail\CoachMonthlyBillingMail;
use App\Models\Coach\Coach;
use App\Services\Coach\CoachService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateCoachBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coaches:generate-billing 
                            {--month= : Month to bill (YYYY-MM format, defaults to last month)}
                            {--coach= : Specific coach ID to bill (optional)}
                            {--force : Override existing monthly billing entry (admin override)}
                            {--dry-run : Show what would be billed without sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly billing reports for coaches and send them via email';

    protected CoachService $coachService;

    /**
     * Create a new command instance.
     */
    public function __construct(CoachService $coachService)
    {
        parent::__construct();
        $this->coachService = $coachService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Determine billing period
        if ($this->option('month')) {
            try {
                $billingDate = Carbon::createFromFormat('Y-m', $this->option('month'));
            } catch (\Exception $e) {
                $this->error('Invalid month format. Use YYYY-MM (e.g., 2024-03)');
                return Command::FAILURE;
            }
        } else {
            // Default to last month
            $billingDate = Carbon::now()->subMonth();
        }

        $year = $billingDate->year;
        $month = $billingDate->month;
        $monthName = $billingDate->copy()->locale('de')->translatedFormat('F Y');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Generating coach billing for {$monthName}...");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No emails will be sent");
        }

        if ($force) {
            $this->warn('FORCE MODE - Bestehende Monatsabrechnungen werden für den Zeitraum überschrieben.');
        }

        // Get coaches to bill
        if ($coachId = $this->option('coach')) {
            $coaches = Coach::where('id', $coachId)->get();
            
            if ($coaches->isEmpty()) {
                $this->error("Coach with ID {$coachId} not found.");
                return Command::FAILURE;
            }
        } else {
            $coaches = Coach::where('active', true)->get();
        }

        if ($coaches->isEmpty()) {
            $this->info("No coaches found to bill.");
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Processing {$coaches->count()} coach(es)...");
        $this->newLine();

        $totalProcessed = 0;
        $totalSent = 0;
        $totalSkipped = 0;
        $grandTotal = 0;

        foreach ($coaches as $coach) {
            /** @var Coach $coach */
            $totalProcessed++;
            $overwriteExistingBilling = false;

            if ($this->coachService->hasMonthlyBilling($coach, $year, $month)) {
                if (!$force) {
                    $this->warn("Coach: {$coach->name}");
                    $this->warn('  ⚠ Bereits abgerechnet - wird übersprungen (kein erneutes Erstellen/Aktualisieren/Versenden).');
                    $totalSkipped++;
                    $this->newLine();
                    continue;
                }

                if (!$dryRun) {
                    $overwriteExistingBilling = true;
                }
            }

            // Calculate billing
            $billingData = $this->coachService->calculateMonthlyBilling($coach, $year, $month);
            $status = 'generated';
            $mailRecipient = $coach->user?->email;
            $mailSentAt = null;
            $notes = null;

            $this->line("Coach: <fg=cyan>{$coach->name}</>");
            $this->line("  Slots: {$billingData['total_slots']}");
            $this->line("  Compensation: € " . number_format($billingData['total_compensation'], 2, ',', '.'));

            if (($billingData['total_slots'] ?? 0) === 0) {
                $this->warn('  ⚠ Keine stattgefundenen Kurse im Zeitraum - kein Eintrag und keine E-Mail.');
                $totalSkipped++;
                $this->newLine();
                continue;
            }

            if ($overwriteExistingBilling) {
                $this->coachService->deleteMonthlyBilling($coach, $year, $month);
                $this->warn('  ⚠ Vorhandene Monatsabrechnung wurde per FORCE gelöscht und wird neu erstellt.');
            }

            $grandTotal += $billingData['total_compensation'];

            // Check if coach has email (via user relationship)
            if (!$coach->user || !$coach->user->email) {
                $this->warn("  ⚠ Skipped: No email address found");
                $totalSkipped++;
                $status = 'skipped_no_email';
                $notes = 'No coach email available';

                $this->coachService->persistMonthlyBilling($billingData, [
                    'status' => $status,
                    'mail_recipient' => $mailRecipient,
                    'mail_sent_at' => $mailSentAt,
                    'notes' => $notes,
                ]);

                $this->newLine();
                continue;
            }

            // Send email
            if (!$dryRun && $billingData['total_compensation'] > 0) {
                try {
                    Mail::to($coach->user->email)
                        ->bcc([config('mail.manager_mail'), config('mail.finance_mail')])
                        ->send(new CoachMonthlyBillingMail($billingData));
                    
                    $this->info("  ✓ Email sent to: {$coach->user->email}");
                    $totalSent++;
                    $status = 'emailed';
                    $mailSentAt = now();
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to send email: " . $e->getMessage());
                    $totalSkipped++;
                    $status = 'email_failed';
                    $notes = $e->getMessage();
                }
            } else {
                $this->info("  → Would send email to: {$coach->user->email}");
                $status = $dryRun ? 'dry_run' : 'generated';
                $notes = $dryRun ? 'Dry run: no email sent' : null;

                if ($dryRun) {
                    $totalSent++;
                }
            }

            $this->coachService->persistMonthlyBilling($billingData, [
                'status' => $status,
                'mail_recipient' => $mailRecipient,
                'mail_sent_at' => $mailSentAt,
                'notes' => $notes,
            ]);

            $this->newLine();
        }

        // Summary
        $this->info("=== Summary ===");
        $this->line("Period: {$monthName}");
        $this->line("Coaches processed: {$totalProcessed}");
        $this->line("Emails sent: {$totalSent}");
        
        if ($totalSkipped > 0) {
            $this->warn("Skipped: {$totalSkipped}");
        }
        
        $this->newLine();
        $this->info("Total compensation: € " . number_format($grandTotal, 2, ',', '.'));

        return Command::SUCCESS;
    }
}

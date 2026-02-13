<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Command;

class ProcessMemberships extends Command
{
    protected $signature = 'memberships:process {--date= : Process date (Y-m-d format)}';

    protected $description = 'Process and assign memberships for all eligible members';

    public function handle(MembershipService $service): int
    {
        $date = $this->option('date');

        $this->info('Starting membership processing...');
        
        $results = $service->processAllMemberships($date);

        $this->newLine();
        $this->info("Processed: {$results['processed']} members");
        $this->info("Created: {$results['created']} memberships");
        $this->info("Skipped: {$results['skipped']} members");

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->error("- {$error['member_name']} (ID: {$error['member_id']}): {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('Membership processing completed!');

        return self::SUCCESS;
    }
}

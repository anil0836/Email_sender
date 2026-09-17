<?php

namespace App\Console\Commands;

use App\Services\SalesforceLeadSyncService;
use Illuminate\Console\Command;

class SyncSalesforceLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:sync-leads 
                            {--full : Force a full synchronization instead of incremental} 
                            {--limit= : Maximum number of records to synchronize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import and synchronize Lead records from Salesforce into the local database';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceLeadSyncService $syncService): int
    {
        $isFull = (bool) $this->option('full');
        $limitOption = $this->option('limit');
        $limit = $limitOption ? (int) $limitOption : null;

        $this->info('====================================================');
        $this->info('🔄 Starting Salesforce Lead Synchronization...');
        $this->info('Mode: ' . ($isFull ? 'FULL SYNC' : 'INCREMENTAL SYNC'));
        if ($limit) {
            $this->info("Record Limit: {$limit}");
        }
        $this->info('====================================================');

        $result = $syncService->syncLeads($isFull, 'artisan', $limit);

        if ($result['success'] ?? false) {
            $this->newLine();
            $this->info('✅ Salesforce Lead Synchronization Completed Successfully!');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Sync Mode', $result['sync_type'] ?? ($isFull ? 'full' : 'incremental')],
                    ['Total Records Fetched', number_format($result['total_fetched'] ?? 0)],
                    ['Newly Created Records', number_format($result['created'] ?? 0)],
                    ['Updated Records', number_format($result['updated'] ?? 0)],
                    ['Skipped Records', number_format($result['skipped'] ?? 0)],
                    ['Failed Records', number_format($result['failed'] ?? 0)],
                    ['Duration', ($result['duration_seconds'] ?? 0) . ' seconds'],
                    ['Checkpoint Timestamp', $result['last_checkpoint'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->error('❌ Salesforce Lead Synchronization Failed:');
        $this->error($result['error'] ?? 'Unknown error occurred.');
        $this->newLine();

        return Command::FAILURE;
    }
}

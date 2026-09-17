<?php

namespace App\Console\Commands;

use App\Services\SalesforceSfUserSyncService;
use Illuminate\Console\Command;

class SyncSalesforceSfUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:sync-sf-users
                            {--full : Force full sync ignoring latest checkpoint}
                            {--limit= : Limit the number of records to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Salesforce Custom Users (SF_User__c) into local database';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceSfUserSyncService $syncService): int
    {
        $this->info('Starting Salesforce Custom User (SF_User__c) synchronization...');

        $forceFull = (bool) $this->option('full');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($forceFull) {
            $this->warn('Running FULL sync mode (all Salesforce SF_User__c records).');
        } else {
            $this->line('Running incremental sync mode.');
        }

        if ($limit) {
            $this->line("Record limit set to: {$limit}");
        }

        $result = $syncService->syncSfUsers($forceFull, 'artisan', $limit);

        if ($result['success'] ?? false) {
            $this->newLine();
            $this->info('✓ ' . ($result['message'] ?? 'Salesforce SF Users synced successfully.'));
            $this->table(
                ['Metric', 'Count / Value'],
                [
                    ['Total Fetched', $result['total_fetched'] ?? 0],
                    ['New SF Users Created', $result['created'] ?? 0],
                    ['Existing SF Users Updated', $result['updated'] ?? 0],
                    ['Skipped', $result['skipped'] ?? 0],
                    ['Failed', $result['failed'] ?? 0],
                    ['Duration', ($result['duration_seconds'] ?? 0) . 's'],
                    ['Latest Checkpoint', $result['last_checkpoint'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->error('✗ Salesforce SF_User__c sync failed: ' . ($result['error'] ?? 'Unknown error'));

        return Command::FAILURE;
    }
}

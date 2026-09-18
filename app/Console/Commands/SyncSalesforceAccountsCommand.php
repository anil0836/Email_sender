<?php

namespace App\Console\Commands;

use App\Services\SalesforceAccountSyncService;
use Illuminate\Console\Command;

class SyncSalesforceAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:sync-accounts
                            {--full : Force full sync ignoring latest checkpoint}
                            {--limit= : Limit the number of records to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Salesforce Accounts into local database';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceAccountSyncService $syncService): int
    {
        $this->info('Starting Salesforce Account synchronization...');

        $forceFull = (bool) $this->option('full');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($forceFull) {
            $this->warn('Running FULL sync mode (all Salesforce Account records).');
        } else {
            $this->line('Running incremental sync mode.');
        }

        if ($limit) {
            $this->line("Record limit set to: {$limit}");
        }

        $result = $syncService->syncAccounts($forceFull, 'artisan', $limit);

        if ($result['success'] ?? false) {
            $this->newLine();
            $this->info('✓ ' . ($result['message'] ?? 'Salesforce Accounts synced successfully.'));
            $this->table(
                ['Metric', 'Count / Value'],
                [
                    ['Total Fetched', $result['total_fetched'] ?? 0],
                    ['New Accounts Created', $result['created'] ?? 0],
                    ['Existing Accounts Updated', $result['updated'] ?? 0],
                    ['Skipped', $result['skipped'] ?? 0],
                    ['Failed', $result['failed'] ?? 0],
                    ['Duration', ($result['duration_seconds'] ?? 0) . 's'],
                    ['Latest Checkpoint', $result['last_checkpoint'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->error('✗ Salesforce Account sync failed: ' . ($result['error'] ?? 'Unknown error'));

        return Command::FAILURE;
    }
}

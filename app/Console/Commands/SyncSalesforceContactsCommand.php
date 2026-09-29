<?php

namespace App\Console\Commands;

use App\Services\SalesforceContactSyncService;
use Illuminate\Console\Command;

class SyncSalesforceContactsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:sync-contacts
                            {--full : Force full sync ignoring latest checkpoint}
                            {--today : Only synchronize records starting from today}
                            {--from-today : Alias for --today}
                            {--order=DESC : Sort order (DESC for latest records first, ASC for oldest)}
                            {--limit= : Limit the number of records to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Salesforce Contacts into local database (defaults to latest records first)';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceContactSyncService $syncService): int
    {
        $this->info('Starting Salesforce Contact synchronization...');

        $forceFull = (bool) $this->option('full');
        $todayOnly = (bool) $this->option('today') || (bool) $this->option('from-today');
        $order = strtoupper((string) ($this->option('order') ?: 'DESC'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($todayOnly) {
            $this->warn('Running TODAY ONLY sync mode (starting from today).');
        } elseif ($forceFull) {
            $this->warn('Running FULL sync mode (all Salesforce Contact records).');
        } else {
            $this->line('Running incremental sync mode.');
        }

        if ($limit) {
            $this->line("Record limit set to: {$limit}");
        }

        $result = $syncService->syncContacts($forceFull, 'artisan', $limit, $todayOnly, $order);

        if ($result['success'] ?? false) {
            $this->newLine();
            $this->info('✓ ' . ($result['message'] ?? 'Salesforce Contacts synced successfully.'));
            $this->table(
                ['Metric', 'Count / Value'],
                [
                    ['Total Fetched', $result['total_fetched'] ?? 0],
                    ['New Contacts Created', $result['created'] ?? 0],
                    ['Existing Contacts Updated', $result['updated'] ?? 0],
                    ['Skipped', $result['skipped'] ?? 0],
                    ['Failed', $result['failed'] ?? 0],
                    ['Duration', ($result['duration_seconds'] ?? 0) . 's'],
                    ['Latest Checkpoint', $result['last_checkpoint'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->error('✗ Salesforce Contact sync failed: ' . ($result['error'] ?? 'Unknown error'));

        return Command::FAILURE;
    }
}

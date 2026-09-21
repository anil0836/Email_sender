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
                            {--today : Only synchronize records starting from today}
                            {--from-today : Alias for --today}
                            {--order=DESC : Sort order (DESC for latest records first, ASC for oldest)}
                            {--limit= : Maximum number of records to synchronize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import and synchronize Lead records from Salesforce into the local database (defaults to latest records first)';

    /**
     * Execute the console command.
     */
    public function handle(SalesforceLeadSyncService $syncService): int
    {
        $isFull = (bool) $this->option('full');
        $todayOnly = (bool) $this->option('today') || (bool) $this->option('from-today');
        $order = strtoupper((string) ($this->option('order') ?: 'DESC'));
        $limitOption = $this->option('limit');
        $limit = $limitOption ? (int) $limitOption : null;

        $modeLabel = $todayOnly ? 'TODAY ONLY (Latest First)' : ($isFull ? 'FULL SYNC' : 'INCREMENTAL SYNC');

        $this->info('====================================================');
        $this->info('🔄 Starting Salesforce Lead Synchronization...');
        $this->info('Mode: ' . $modeLabel);
        $this->info('Order: ' . $order . ' (starting from ' . ($order === 'DESC' ? 'latest/today to last' : 'oldest to latest') . ')');
        if ($limit) {
            $this->info("Record Limit: {$limit}");
        }
        $this->info('====================================================');

        $result = $syncService->syncLeads($isFull, 'artisan', $limit, $todayOnly, $order);

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
                    ['Owners Mapped', number_format($result['owners_mapped'] ?? 0)],
                    ['Owners Not Mapped', number_format($result['owners_not_mapped'] ?? 0)],
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

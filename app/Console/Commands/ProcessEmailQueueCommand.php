<?php

namespace App\Console\Commands;

use App\Services\CampaignProcessingService;
use Illuminate\Console\Command;

class ProcessEmailQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-queue
                            {--limit= : Maximum number of recipient emails to dispatch in this execution cycle}
                            {--timeout=55 : Maximum execution seconds for this command before cleanly yielding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process queued and scheduled bulk email campaigns with rate limiting and concurrency safety';

    /**
     * Execute the console command.
     */
    public function handle(CampaignProcessingService $processingService): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $timeout = $this->option('timeout') !== null ? (int) $this->option('timeout') : 55;

        $this->info("Starting bulk email queue processing (timeout: {$timeout}s, limit: " . ($limit ?: 'unlimited') . ')...');
        $processed = $processingService->processQueuedEmails($limit, $timeout);
        $this->info("Completed processing. {$processed} email(s) dispatched.");

        return Command::SUCCESS;
    }
}

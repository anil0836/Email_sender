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
    protected $signature = 'email:process-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process queued and scheduled bulk email campaigns';

    /**
     * Execute the console command.
     */
    public function handle(CampaignProcessingService $processingService): int
    {
        $this->info('Starting bulk email queue processing...');
        $processed = $processingService->processQueuedEmails();
        $this->info("Completed processing. {$processed} emails dispatched.");

        return Command::SUCCESS;
    }
}

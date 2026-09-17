<?php

namespace App\Console\Commands;

use App\Services\SalesforceAccountSyncService;
use App\Services\SalesforceContactSyncService;
use App\Services\SalesforceLeadSyncService;
use App\Services\SalesforceSfUserSyncService;
use App\Services\SalesforceUserSyncService;
use Illuminate\Console\Command;

class SyncSalesforceAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:sync-all
                            {--full : Force full sync ignoring latest checkpoints}
                            {--limit= : Limit the number of records per object}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize all Salesforce entities (Users, SF Custom Users, Accounts, Contacts, Leads) in dependency order';

    /**
     * Execute the console command.
     */
    public function handle(
        SalesforceUserSyncService $userService,
        SalesforceSfUserSyncService $sfUserService,
        SalesforceAccountSyncService $accountService,
        SalesforceContactSyncService $contactService,
        SalesforceLeadSyncService $leadService
    ): int {
        $this->info('====================================================');
        $this->info('Starting Full Salesforce Master Synchronization');
        $this->info('====================================================');

        $forceFull = (bool) $this->option('full');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // 1. Sync Standard Users
        $this->line('--> [1/5] Syncing Salesforce Standard Users...');
        $userResult = $userService->syncUsers($forceFull, 'artisan', $limit);
        if ($userResult['success'] ?? false) {
            $this->info("    ✓ Standard Users: {$userResult['created']} created, {$userResult['updated']} updated ({$userResult['total_fetched']} fetched)");
        } else {
            $this->warn("    ✗ Standard Users sync warning: " . ($userResult['error'] ?? 'Failed'));
        }

        // 2. Sync Custom SF Users (SF_User__c)
        $this->line('--> [2/5] Syncing Salesforce Custom Users (SF_User__c)...');
        $sfUserResult = $sfUserService->syncSfUsers($forceFull, 'artisan', $limit);
        if ($sfUserResult['success'] ?? false) {
            $this->info("    ✓ SF Users (SF_User__c): {$sfUserResult['created']} created, {$sfUserResult['updated']} updated ({$sfUserResult['total_fetched']} fetched)");
        } else {
            $this->warn("    ✗ SF Users sync warning: " . ($sfUserResult['error'] ?? 'Failed'));
        }

        // 3. Sync Accounts
        $this->line('--> [3/5] Syncing Salesforce Accounts...');
        $accountResult = $accountService->syncAccounts($forceFull, 'artisan', $limit);
        if ($accountResult['success'] ?? false) {
            $this->info("    ✓ Accounts: {$accountResult['created']} created, {$accountResult['updated']} updated ({$accountResult['total_fetched']} fetched)");
        } else {
            $this->warn("    ✗ Accounts sync warning: " . ($accountResult['error'] ?? 'Failed'));
        }

        // 4. Sync Contacts
        $this->line('--> [4/5] Syncing Salesforce Contacts...');
        $contactResult = $contactService->syncContacts($forceFull, 'artisan', $limit);
        if ($contactResult['success'] ?? false) {
            $this->info("    ✓ Contacts: {$contactResult['created']} created, {$contactResult['updated']} updated ({$contactResult['total_fetched']} fetched)");
        } else {
            $this->warn("    ✗ Contacts sync warning: " . ($contactResult['error'] ?? 'Failed'));
        }

        // 5. Sync Leads
        $this->line('--> [5/5] Syncing Salesforce Leads...');
        $leadResult = $leadService->syncLeads($forceFull, 'artisan', $limit);
        if ($leadResult['success'] ?? false) {
            $this->info("    ✓ Leads: {$leadResult['created']} created, {$leadResult['updated']} updated ({$leadResult['total_fetched']} fetched)");
        } else {
            $this->warn("    ✗ Leads sync warning: " . ($leadResult['error'] ?? 'Failed'));
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('✓ Salesforce Master Synchronization Cycle Completed');
        $this->info('====================================================');

        return Command::SUCCESS;
    }
}

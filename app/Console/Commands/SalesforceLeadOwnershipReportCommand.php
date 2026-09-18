<?php

namespace App\Console\Commands;

use App\Models\SalesforceAccount;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use Illuminate\Console\Command;

class SalesforceLeadOwnershipReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:lead-ownership-report
                            {--type=all : Which ownership type to display: all, sf_user, or standard_user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display summary report of all Users and SF_Users with their identified Lead, Account, and Contact records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = strtolower($this->option('type') ?: 'all');

        $totalLeads = SalesforceLead::count();
        $totalAccounts = SalesforceAccount::count();
        $totalContacts = SalesforceContact::count();
        $totalSfUsers = SalesforceSfUser::count();
        $totalStandardUsers = SalesforceUser::count();

        $this->newLine();
        $this->info('========================================================================================');
        $this->info('              SALESFORCE USER & RECORD OWNERSHIP MAPPING REPORT                         ');
        $this->info('========================================================================================');
        $this->line("Database Totals: {$totalSfUsers} SF_Users | {$totalStandardUsers} Standard Users | {$totalLeads} Leads | {$totalAccounts} Accounts | {$totalContacts} Contacts");
        $this->newLine();

        if (in_array($type, ['all', 'sf_user'])) {
            $this->info('--- CUSTOM USERS (SF_User__c) -> PRIME OWNERSHIP BREAKDOWN ---');

            $sfUsers = SalesforceSfUser::withCount(['primeLeads', 'primeAccounts', 'primeContacts'])
                ->orderByDesc('prime_leads_count')
                ->get();

            $sfUserData = [];
            foreach ($sfUsers as $u) {
                if ($u->prime_leads_count > 0 || $u->prime_accounts_count > 0 || $u->prime_contacts_count > 0) {
                    $sfUserData[] = [
                        $u->salesforce_id,
                        $u->name,
                        $u->emp_name ?: '-',
                        $u->emp_code ?: '-',
                        $u->process ?: '-',
                        $u->is_active ? 'Active' : 'Inactive',
                        $u->prime_leads_count,
                        $u->prime_accounts_count,
                        $u->prime_contacts_count,
                    ];
                }
            }

            if (empty($sfUserData)) {
                $this->warn('No SF_User__c records currently have prime-owned records in the local database.');
            } else {
                $this->table(
                    ['SF_User ID', 'Name', 'Full Name (IN)', 'Emp Code', 'Process', 'Status', 'Leads', 'Accounts', 'Contacts'],
                    $sfUserData
                );
            }
            $this->newLine();
        }

        if (in_array($type, ['all', 'standard_user'])) {
            $this->info('--- STANDARD USERS (User) -> STANDARD OWNERSHIP BREAKDOWN ---');

            $standardUsers = SalesforceUser::withCount(['leads', 'accounts', 'contacts'])
                ->orderByDesc('leads_count')
                ->get();

            $stdUserData = [];
            foreach ($standardUsers as $su) {
                if ($su->leads_count > 0 || $su->accounts_count > 0 || $su->contacts_count > 0) {
                    $stdUserData[] = [
                        $su->salesforce_id,
                        $su->name,
                        $su->username,
                        $su->email ?: '-',
                        $su->is_active ? 'Active' : 'Inactive',
                        $su->leads_count,
                        $su->accounts_count,
                        $su->contacts_count,
                    ];
                }
            }

            if (empty($stdUserData)) {
                $this->warn('No standard User records currently have owned records in the local database.');
            } else {
                $this->table(
                    ['User ID', 'Name', 'Username', 'Email', 'Status', 'Leads', 'Accounts', 'Contacts'],
                    $stdUserData
                );
            }
            $this->newLine();
        }

        $this->info('========================================================================================');
        return Command::SUCCESS;
    }
}

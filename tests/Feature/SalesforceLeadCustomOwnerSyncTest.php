<?php

namespace Tests\Feature;

use App\Models\SalesforceLead;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Services\SalesforceLeadSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforceLeadCustomOwnerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected SalesforceLeadSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalesforceLeadSyncService::class);
    }

    /**
     * Test Priority 1: Exact match on salesforce_sf_users.name
     */
    public function test_custom_owner_maps_to_local_sf_user_by_exact_name()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX001',
            'name' => 'Alice Johnson',
            'emp_name' => 'Alice Johnson',
            'emp_email' => 'alice@example.com',
            'is_active' => true,
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX001',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'name' => 'John Smith',
                'company' => 'Acme Corp',
                'email' => 'john.smith@acme.test',
                'custom_owner' => 'Alice Johnson',
                'status' => 'New',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(1, $stats['owners_mapped']);
        $this->assertEquals(0, $stats['owners_not_mapped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX001')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Alice Johnson', $lead->custom_owner);
        $this->assertEquals('Alice Johnson', $lead->Custom_Owner__c);
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
        $this->assertEquals($sfUser->salesforce_id, $lead->prime_owner_id);
        $this->assertEquals('Alice Johnson', $lead->owner_name);
        $this->assertEquals('alice@example.com', $lead->owner_email);

        // Verify relationship
        $this->assertNotNull($lead->salesforceOwner);
        $this->assertEquals($sfUser->id, $lead->salesforceOwner->id);
        $this->assertTrue($sfUser->leads->contains($lead));
    }

    /**
     * Test case-insensitivity and whitespace trimming for Custom_Owner__c matching
     */
    public function test_custom_owner_matching_is_case_insensitive_and_whitespace_tolerant()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX002',
            'name' => 'Robert "Bob" Taylor',
            'emp_name' => 'Bob Taylor',
            'emp_email' => 'bob.taylor@example.com',
            'is_active' => true,
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX002',
                'name' => 'Jane Doe',
                'custom_owner' => '  robert "bob" taylor   ',
                'status' => 'Working',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(1, $stats['owners_mapped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX002')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('  robert "bob" taylor   ', $lead->custom_owner);
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('Robert "Bob" Taylor', $lead->owner_name);
        $this->assertEquals('bob.taylor@example.com', $lead->owner_email);
    }

    /**
     * Test fallback to emp_name when name does not match directly
     */
    public function test_custom_owner_matches_emp_name_fallback()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX003',
            'name' => 'EMP-0099',
            'emp_name' => 'Charlie Brown',
            'emp_email' => 'charlie@example.com',
            'is_active' => true,
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX003',
                'name' => 'Lucy Van Pelt',
                'custom_owner' => 'Charlie Brown',
                'status' => 'New',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(1, $stats['owners_mapped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX003')->first();
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('charlie@example.com', $lead->owner_email);
    }

    /**
     * Test unmatched Custom_Owner__c falls back to Prime Owner and does not crash
     */
    public function test_unmatched_custom_owner_increments_metric_and_falls_back_to_prime_owner()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX004',
            'name' => 'Prime Fallback User',
            'emp_name' => 'Prime Fallback',
            'emp_email' => 'prime@example.com',
            'is_active' => true,
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX004',
                'name' => 'Unmatched Custom Owner Lead',
                'custom_owner' => 'Non Existent Person In System',
                'prime_owner_id' => 'a04XXXXXXXXXXXX004',
                'status' => 'New',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['owners_mapped']);
        $this->assertEquals(1, $stats['owners_not_mapped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX004')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Non Existent Person In System', $lead->custom_owner);
        // salesforce_sf_user_id resolved via Priority 2 (prime_owner_id fallback)
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('Prime Fallback User', $lead->owner_name);
        $this->assertEquals('prime@example.com', $lead->owner_email);
    }

    /**
     * Test unmatched Custom_Owner__c with no prime owner falls back to standard user (owner_id)
     */
    public function test_unmatched_custom_owner_falls_back_to_standard_owner_id()
    {
        $standardUser = SalesforceUser::create([
            'salesforce_id' => '005XXXXXXXXXXXX001',
            'name' => 'Standard SF Agent',
            'email' => 'agent@example.com',
            'username' => 'agent@example.com',
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX005',
                'name' => 'Standard Owner Lead',
                'custom_owner' => 'Unknown Owner Guy',
                'owner_id' => '005XXXXXXXXXXXX001',
                'status' => 'New',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['owners_mapped']);
        $this->assertEquals(1, $stats['owners_not_mapped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX005')->first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->salesforce_sf_user_id);
        $this->assertEquals('Standard SF Agent', $lead->owner_name);
        $this->assertEquals('agent@example.com', $lead->owner_email);
    }

    /**
     * Test updating existing lead when Custom_Owner__c changes
     */
    public function test_existing_lead_updates_owner_mapping_when_custom_owner_changes()
    {
        $userA = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX00A',
            'name' => 'User Alpha',
            'emp_email' => 'alpha@example.com',
        ]);

        $userB = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX00B',
            'name' => 'User Beta',
            'emp_email' => 'beta@example.com',
        ]);

        // Initial sync with User Alpha
        $this->service->processLeadRecords([
            [
                'salesforce_id' => '00QXXXXXXXXXXXX006',
                'name' => 'Changing Owner Lead',
                'custom_owner' => 'User Alpha',
                'status' => 'New',
            ]
        ]);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX006')->first();
        $this->assertEquals($userA->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('User Alpha', $lead->owner_name);

        // Second sync reassigns to User Beta
        $stats = $this->service->processLeadRecords([
            [
                'salesforce_id' => '00QXXXXXXXXXXXX006',
                'name' => 'Changing Owner Lead',
                'custom_owner' => 'User Beta',
                'status' => 'Working',
            ]
        ]);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['updated']);
        $this->assertEquals(1, $stats['owners_mapped']);

        $lead->refresh();
        $this->assertEquals($userB->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('User Beta', $lead->owner_name);
        $this->assertEquals('beta@example.com', $lead->owner_email);
    }

    /**
     * Test duplicate prevention: sync never creates records in salesforce_sf_users
     */
    public function test_lead_sync_never_creates_records_in_salesforce_sf_users()
    {
        SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX999',
            'name' => 'Existing User',
            'emp_email' => 'existing@example.com',
        ]);

        $initialUserCount = SalesforceSfUser::count();

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX101',
                'name' => 'Lead One',
                'custom_owner' => 'Brand New Unknown User Name',
            ],
            [
                'salesforce_id' => '00QXXXXXXXXXXXX102',
                'name' => 'Lead Two',
                'custom_owner' => 'Another Unknown User',
            ]
        ];

        $this->service->processLeadRecords($rawRecords);

        // SF Users count must remain completely unchanged
        $this->assertEquals($initialUserCount, SalesforceSfUser::count());
    }

    /**
     * Test scopes filterCustomOwner and filterSalesforceSfUser
     */
    public function test_salesforce_lead_scopes_filter_by_custom_owner_and_sf_user()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX888',
            'name' => 'Scoped User',
            'emp_email' => 'scoped@example.com',
        ]);

        $lead = SalesforceLead::create([
            'salesforce_id' => '00QXXXXXXXXXXXX888',
            'name' => 'Scoped Lead',
            'custom_owner' => 'Scoped User',
            'salesforce_sf_user_id' => $sfUser->id,
            'status' => 'New',
        ]);

        $this->assertCount(1, SalesforceLead::filterCustomOwner('Scoped User')->get());
        $this->assertCount(0, SalesforceLead::filterCustomOwner('Nonexistent')->get());

        $this->assertCount(1, SalesforceLead::filterSalesforceSfUser($sfUser->id)->get());
        $this->assertCount(0, SalesforceLead::filterSalesforceSfUser(99999)->get());
    }

    /**
     * Test raw Salesforce keys (Custom_Owner__c, OwnerId, Prime_Owner__c) are correctly processed
     */
    public function test_raw_salesforce_field_keys_are_supported()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX999',
            'name' => 'Raw Key User',
            'emp_name' => 'Raw Key User',
            'emp_email' => 'rawkey@example.com',
            'is_active' => true,
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX999',
                'name' => 'Raw Key Lead',
                'Custom_Owner__c' => 'Raw Key User',
                'OwnerId' => '005XXXXXXXXXXXX999',
                'Prime_Owner__c' => 'a04XXXXXXXXXXXX999',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(1, $stats['owners_mapped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX999')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Raw Key User', $lead->custom_owner);
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('Raw Key User', $lead->owner_name);
    }

    /**
     * Test Priority 3: When Custom_Owner__c is null on update, existing valid owner is preserved
     */
    public function test_priority_3_preserves_existing_valid_owner_when_custom_owner_is_null()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX777',
            'name' => 'Initial Owner',
            'emp_email' => 'initial@example.com',
        ]);

        // Create lead with initial owner
        $lead = SalesforceLead::create([
            'salesforce_id' => '00QXXXXXXXXXXXX777',
            'name' => 'Preserve Owner Lead',
            'custom_owner' => 'Initial Owner',
            'salesforce_sf_user_id' => $sfUser->id,
            'owner_name' => 'Initial Owner',
            'owner_email' => 'initial@example.com',
            'status' => 'New',
        ]);

        // Subsequent update arrives with Custom_Owner__c = null and no prime owner
        $updateRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX777',
                'name' => 'Preserve Owner Lead Updated',
                'custom_owner' => null,
                'status' => 'Working',
            ]
        ];

        $stats = $this->service->processLeadRecords($updateRecords);

        $this->assertEquals(1, $stats['updated']);

        $lead->refresh();
        $this->assertEquals('Preserve Owner Lead Updated', $lead->name);
        // Owner must be preserved, not overwritten with null!
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
        $this->assertEquals('Initial Owner', $lead->owner_name);
        $this->assertEquals('initial@example.com', $lead->owner_email);
    }

    /**
     * Test Custom_Owner__c column exists in salesforce_leads table and is populated during synchronization
     */
    public function test_custom_owner_c_database_column_is_populated_and_synchronized()
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('salesforce_leads', 'Custom_Owner__c'));

        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX555',
            'name' => 'Column Test User',
            'emp_name' => 'Column Test User',
            'emp_email' => 'columntest@example.com',
            'is_active' => true,
        ]);

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX555',
                'name' => 'Column Test Lead',
                'Custom_Owner__c' => 'Column Test User',
                'status' => 'New',
            ]
        ];

        $this->service->processLeadRecords($rawRecords);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX555')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Column Test User', $lead->Custom_Owner__c);
        $this->assertEquals('Column Test User', $lead->custom_owner);
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
    }

    /**
     * Test leads with IsConverted = false are stored in salesforce_leads
     */
    public function test_unconverted_leads_are_stored_successfully()
    {
        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX301',
                'name' => 'Active Unconverted Lead',
                'status' => 'Open - Not Contacted',
                'IsConverted' => false,
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['skipped']);

        $lead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX301')->first();
        $this->assertNotNull($lead);
        $this->assertFalse($lead->is_converted);
    }

    /**
     * Test leads with IsConverted = true are NOT stored in salesforce_leads
     */
    public function test_converted_leads_are_not_stored()
    {
        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX302',
                'name' => 'Converted Lead Should Be Skipped',
                'status' => 'Closed - Converted',
                'IsConverted' => true,
            ],
            [
                'salesforce_id' => '00QXXXXXXXXXXXX303',
                'name' => 'Another Converted Lead',
                'status' => 'Closed - Converted',
                'is_converted' => 'true',
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(2, $stats['skipped']);

        $this->assertDatabaseMissing('salesforce_leads', ['salesforce_id' => '00QXXXXXXXXXXXX302']);
        $this->assertDatabaseMissing('salesforce_leads', ['salesforce_id' => '00QXXXXXXXXXXXX303']);
    }

    /**
     * Test if an existing lead becomes converted in Salesforce, it is removed from salesforce_leads
     */
    public function test_existing_lead_is_deleted_when_converted_in_salesforce()
    {
        $lead = SalesforceLead::create([
            'salesforce_id' => '00QXXXXXXXXXXXX304',
            'name' => 'Lead That Gets Converted',
            'status' => 'Working - Contacted',
            'is_converted' => false,
        ]);

        $this->assertDatabaseHas('salesforce_leads', ['salesforce_id' => '00QXXXXXXXXXXXX304']);

        // Sync arrives with IsConverted = true
        $updateRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX304',
                'name' => 'Lead That Gets Converted',
                'status' => 'Closed - Converted',
                'IsConverted' => true,
            ]
        ];

        $stats = $this->service->processLeadRecords($updateRecords);

        $this->assertEquals(0, $stats['updated']);
        $this->assertEquals(1, $stats['skipped']);

        // Must no longer exist as an active lead in salesforce_leads table
        $this->assertDatabaseMissing('salesforce_leads', ['salesforce_id' => '00QXXXXXXXXXXXX304']);
    }

    /**
     * Test processing leads received in descending order (latest first) correctly persists latest dates and custom owner
     */
    public function test_process_lead_records_preserves_latest_dates_and_descending_records()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04XXXXXXXXXXXX305',
            'name' => 'Latest Lead Owner',
            'emp_name' => 'Latest Lead Owner',
            'emp_email' => 'latest.owner@example.com',
            'is_active' => true,
        ]);

        $todayIso = Carbon::now()->toIso8601String();
        $yesterdayIso = Carbon::now()->subDay()->toIso8601String();

        $rawRecords = [
            [
                'salesforce_id' => '00QXXXXXXXXXXXX305',
                'name' => 'Today Latest Lead',
                'company' => 'Modern Tech',
                'email' => 'today@moderntech.test',
                'status' => 'New',
                'custom_owner' => 'Latest Lead Owner',
                'IsConverted' => false,
                'salesforce_created_at' => $todayIso,
                'salesforce_updated_at' => $todayIso,
            ],
            [
                'salesforce_id' => '00QXXXXXXXXXXXX306',
                'name' => 'Yesterday Older Lead',
                'company' => 'Older Tech',
                'email' => 'yesterday@oldertech.test',
                'status' => 'Working',
                'custom_owner' => 'Latest Lead Owner',
                'IsConverted' => false,
                'salesforce_created_at' => $yesterdayIso,
                'salesforce_updated_at' => $yesterdayIso,
            ]
        ];

        $stats = $this->service->processLeadRecords($rawRecords);

        $this->assertEquals(2, $stats['created']);
        $this->assertEquals(2, $stats['owners_mapped']);
        $this->assertEquals(0, $stats['skipped']);

        $todayLead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX305')->first();
        $yesterdayLead = SalesforceLead::where('salesforce_id', '00QXXXXXXXXXXXX306')->first();

        $this->assertNotNull($todayLead);
        $this->assertNotNull($yesterdayLead);
        $this->assertEquals('Latest Lead Owner', $todayLead->Custom_Owner__c);
        $this->assertEquals($sfUser->id, $todayLead->salesforce_sf_user_id);
        $this->assertTrue(Carbon::parse($todayLead->salesforce_updated_at)->greaterThan(Carbon::parse($yesterdayLead->salesforce_updated_at)));
    }

    /**
     * Test syncLeads with todayOnly records sync log with sync_type 'today'
     */
    public function test_sync_leads_service_records_today_sync_type()
    {
        // Calling syncLeads with todayOnly = true (process will run with --today)
        $result = $this->service->syncLeads(false, 'cron', 1, true);

        // A sync log entry for Lead must have been created with sync_type 'today'
        $log = \App\Models\SalesforceSyncLog::where('object_type', 'Lead')
            ->where('sync_type', 'today')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('today', $log->sync_type);
    }

    /**
     * Test artisan salesforce:sync-leads command with --today and --limit options
     */
    public function test_artisan_sync_leads_command_with_today_option()
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('salesforce:sync-leads', [
            '--today' => true,
            '--limit' => 1,
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('TODAY ONLY (Latest First)', $output);
        $this->assertStringContainsString('Order: DESC', $output);
    }
}




<?php

namespace Tests\Feature;

use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Services\SalesforceContactSyncService;
use App\Services\SalesforceLeadSyncService;
use App\Services\SalesforceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesforceDealCategoryAndScaleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected SalesforceLeadSyncService $leadSyncService;
    protected SalesforceContactSyncService $contactSyncService;
    protected SalesforceService $salesforceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leadSyncService = app(SalesforceLeadSyncService::class);
        $this->contactSyncService = app(SalesforceContactSyncService::class);
        $this->salesforceService = app(SalesforceService::class);
    }

    /**
     * Test 1: Verify Deal_Category__c column exists in salesforce_leads table with index.
     */
    public function test_deal_category_c_column_exists_in_database()
    {
        $this->assertTrue(
            Schema::hasColumn('salesforce_leads', 'Deal_Category__c'),
            "Column Deal_Category__c must exist on salesforce_leads table"
        );
    }

    /**
     * Test 2: Verify Deal_Category__c can be mass assigned and accessed via Model and Accessor.
     */
    public function test_salesforce_lead_model_fillable_and_accessor()
    {
        $lead = new SalesforceLead([
            'salesforce_id' => '00QTEST000000001',
            'name' => 'Tech Corp',
            'Deal_Category__c' => 'Barebone/Scrap Laptops',
            'industry' => 'Hardware',
        ]);
        $lead->save();

        $this->assertEquals('Barebone/Scrap Laptops', $lead->Deal_Category__c);
        $this->assertEquals('Barebone/Scrap Laptops', $lead->deal_category);

        // Fallback test when Deal_Category__c is null
        $lead2 = new SalesforceLead([
            'salesforce_id' => '00QTEST000000002',
            'name' => 'Tech Corp 2',
            'industry' => 'Electronics',
        ]);
        $lead2->save();

        $this->assertNull($lead2->Deal_Category__c);
        $this->assertEquals('Electronics', $lead2->deal_category);
    }

    /**
     * Test 3: Verify scopes for searching and filtering by Deal_Category__c.
     */
    public function test_salesforce_lead_search_and_filter_by_deal_category()
    {
        SalesforceLead::create([
            'salesforce_id' => '00QTEST000000003',
            'name' => 'Scanner Specialist',
            'Deal_Category__c' => 'Bar Code Scanner',
            'status' => 'New',
        ]);

        SalesforceLead::create([
            'salesforce_id' => '00QTEST000000004',
            'name' => 'Server Warehouse',
            'Deal_Category__c' => 'Servers / Rack Servers',
            'status' => 'New',
        ]);

        $searchResult = SalesforceLead::search('Bar Code Scanner')->get();
        $this->assertCount(1, $searchResult);
        $this->assertEquals('00QTEST000000003', $searchResult->first()->salesforce_id);

        $filterResult = SalesforceLead::filterDealCategory('Servers / Rack Servers')->get();
        $this->assertCount(1, $filterResult);
        $this->assertEquals('00QTEST000000004', $filterResult->first()->salesforce_id);
    }

    /**
     * Test 4: Verify sync correctly stores Deal_Category__c from raw records.
     */
    public function test_lead_sync_stores_deal_category_c()
    {
        $rawRecords = [
            [
                'salesforce_id' => '00QTEST000000005',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'name' => 'John Doe',
                'email' => 'john.doe@test.com',
                'company' => 'Acme Inc',
                'Deal_Category__c' => 'HighEnd Desktops',
                'status' => 'New',
            ],
            [
                'salesforce_id' => '00QTEST000000006',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'name' => 'Jane Smith',
                'email' => 'jane.smith@test.com',
                'company' => 'Beta LLC',
                'deal_category' => 'Laptop I Series',
                'status' => 'New',
            ]
        ];

        $stats = $this->leadSyncService->processLeadRecords($rawRecords);

        $this->assertEquals(2, $stats['created']);

        $lead1 = SalesforceLead::where('salesforce_id', '00QTEST000000005')->first();
        $this->assertNotNull($lead1);
        $this->assertEquals('HighEnd Desktops', $lead1->Deal_Category__c);

        $lead2 = SalesforceLead::where('salesforce_id', '00QTEST000000006')->first();
        $this->assertNotNull($lead2);
        $this->assertEquals('Laptop I Series', $lead2->Deal_Category__c);

        // Verify SalesforceService formatLeadRecord
        $formatted = $this->salesforceService->formatLeadRecord($lead1);
        $this->assertEquals('HighEnd Desktops', $formatted['Deal_Category__c']);
        $this->assertEquals('HighEnd Desktops', $formatted['deal_category']);
    }

    /**
     * Test 5: Verify bulk upsert updates Deal_Category__c without corrupting existing owners.
     */
    public function test_lead_sync_updates_deal_category_preserving_existing_owner()
    {
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04TEST000000001',
            'name' => 'Marcus Aurelius',
            'emp_name' => 'Marcus Aurelius',
            'emp_email' => 'marcus@roman.test',
            'is_active' => true,
        ]);

        // Initial sync with custom owner
        $this->leadSyncService->processLeadRecords([
            [
                'salesforce_id' => '00QTEST000000007',
                'name' => 'Colosseum Tech',
                'custom_owner' => 'Marcus Aurelius',
                'Deal_Category__c' => 'Docking Stations',
                'status' => 'New',
            ]
        ]);

        $lead = SalesforceLead::where('salesforce_id', '00QTEST000000007')->first();
        $this->assertEquals('Marcus Aurelius', $lead->custom_owner);
        $this->assertEquals('Marcus Aurelius', $lead->Custom_Owner__c);
        $this->assertEquals('Docking Stations', $lead->Deal_Category__c);
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);

        // Subsequent incremental sync updating Deal_Category__c without custom_owner present
        $this->leadSyncService->processLeadRecords([
            [
                'salesforce_id' => '00QTEST000000007',
                'name' => 'Colosseum Tech',
                'Deal_Category__c' => 'Gaming PC/Consoles',
                'status' => 'Working',
            ]
        ]);

        $lead->refresh();
        $this->assertEquals('Gaming PC/Consoles', $lead->Deal_Category__c);
        $this->assertEquals('Marcus Aurelius', $lead->custom_owner);
        $this->assertEquals('Marcus Aurelius', $lead->Custom_Owner__c);
        $this->assertEquals($sfUser->id, $lead->salesforce_sf_user_id);
    }

    /**
     * Test 6: Verify streaming JSON Lines file processing for large scale without memory spikes.
     */
    public function test_lead_sync_streamed_file_processing()
    {
        $tempPath = storage_path('app/test_large_scale_' . uniqid() . '.jsonl');
        @mkdir(dirname($tempPath), 0755, true);

        // Generate 1,200 streamed records across multiple chunks
        $handle = fopen($tempPath, 'w');
        for ($i = 1; $i <= 1200; $i++) {
            $record = [
                'salesforce_id' => sprintf('00QSTREAM%08d', $i),
                'first_name' => "LeadFirst{$i}",
                'last_name' => "LeadLast{$i}",
                'name' => "Lead {$i}",
                'email' => "streamed{$i}@example.com",
                'company' => "Stream Corp {$i}",
                'Deal_Category__c' => ($i % 2 === 0) ? 'MacBooks' : 'Chromebook',
                'status' => 'New',
                'is_converted' => false,
            ];
            fwrite($handle, json_encode($record) . "\n");
        }
        fclose($handle);

        $this->assertFileExists($tempPath);

        // Process directly from file stream
        $stats = $this->leadSyncService->processLeadRecords($tempPath);

        @unlink($tempPath);

        $this->assertEquals(1200, $stats['created']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(1200, SalesforceLead::count());

        $first = SalesforceLead::where('salesforce_id', '00QSTREAM00000001')->first();
        $this->assertEquals('Chromebook', $first->Deal_Category__c);

        $second = SalesforceLead::where('salesforce_id', '00QSTREAM00000002')->first();
        $this->assertEquals('MacBooks', $second->Deal_Category__c);
    }

    /**
     * Test 7: Verify converted leads rule (IsConverted = false): converted leads are skipped and deleted if existing.
     */
    public function test_lead_sync_enforces_is_converted_rule()
    {
        // 1. Create an active unconverted lead
        $this->leadSyncService->processLeadRecords([
            [
                'salesforce_id' => '00QCONVERT000001',
                'name' => 'To Convert Lead',
                'is_converted' => false,
                'status' => 'New',
            ]
        ]);
        $this->assertDatabaseHas('salesforce_leads', ['salesforce_id' => '00QCONVERT000001']);

        // 2. Sync again with IsConverted = true (converted in Salesforce)
        $stats = $this->leadSyncService->processLeadRecords([
            [
                'salesforce_id' => '00QCONVERT000001',
                'name' => 'To Convert Lead',
                'is_converted' => true,
                'status' => 'Closed - Converted',
            ],
            [
                'salesforce_id' => '00QCONVERT000002',
                'name' => 'Already Converted Lead',
                'is_converted' => true,
                'status' => 'Closed - Converted',
            ]
        ]);

        $this->assertEquals(2, $stats['skipped']);
        $this->assertEquals(0, $stats['created']);
        // The previously existing lead must be deleted
        $this->assertDatabaseMissing('salesforce_leads', ['salesforce_id' => '00QCONVERT000001']);
        $this->assertDatabaseMissing('salesforce_leads', ['salesforce_id' => '00QCONVERT000002']);
    }

    /**
     * Test 8: Verify Contact sync batching and streaming.
     */
    public function test_contact_sync_streaming_and_batch_upsert()
    {
        $tempPath = storage_path('app/test_contacts_' . uniqid() . '.jsonl');
        @mkdir(dirname($tempPath), 0755, true);

        $handle = fopen($tempPath, 'w');
        for ($i = 1; $i <= 300; $i++) {
            $record = [
                'salesforce_id' => sprintf('003CONTACT%06d', $i),
                'first_name' => "ContactFirst{$i}",
                'last_name' => "ContactLast{$i}",
                'name' => "Contact {$i}",
                'email' => "contact{$i}@domain.test",
                'phone' => "12345678{$i}",
                'department' => 'IT Support',
            ];
            fwrite($handle, json_encode($record) . "\n");
        }
        fclose($handle);

        $stats = $this->contactSyncService->processContactRecords($tempPath);
        @unlink($tempPath);

        $this->assertEquals(300, $stats['created']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(300, SalesforceContact::count());

        $contact = SalesforceContact::where('salesforce_id', '003CONTACT000001')->first();
        $this->assertEquals('Contact 1', $contact->name);
        $this->assertEquals('contact1@domain.test', $contact->email);
    }
}

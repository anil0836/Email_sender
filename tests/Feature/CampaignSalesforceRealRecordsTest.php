<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\RecipientLog;
use App\Models\SalesforceAccount;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Models\SendingDomain;
use App\Models\User;
use App\Services\SalesforceAccountSyncService;
use App\Services\SalesforceContactSyncService;
use App\Services\SalesforceLeadSyncService;
use App\Services\SalesforceService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSalesforceRealRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected SalesforceService $sfService;
    protected User $admin;
    protected User $standardUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->sfService = app(SalesforceService::class);

        $this->admin = User::where('username', 'admin')->first();
        $this->standardUser = User::where('username', 'user')->first();
    }

    public function test_campaign_dispatch_with_real_salesforce_leads_creates_campaign_members_and_tracks_owners(): void
    {
        // 1. Create a real synced Salesforce Lead owned by admin
        $sfUser = SalesforceUser::create([
            'salesforce_id' => '005SF0000000001',
            'name' => 'Alice Walker',
            'username' => 'admin',
            'email' => 'admin@b2bexportsllc.com',
            'is_active' => true,
        ]);

        $lead = SalesforceLead::create([
            'salesforce_id' => '00QREAL000000001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'company' => 'Acme Corporation',
            'email' => 'john.doe@acme.com',
            'phone' => '+1 555-0100',
            'status' => 'Open - Not Contacted',
            'owner_id' => $sfUser->salesforce_id,
            'owner_name' => 'Alice Walker',
            'owner_email' => 'alice@salesforce.com',
            'owner_verification_status' => 'verified',
            'last_owner_verified_at' => Carbon::now(),
            'salesforce_created_at' => Carbon::now()->subDays(10),
            'salesforce_updated_at' => Carbon::now()->subDays(1),
            'synced_at' => Carbon::now(),
        ]);

        // 2. Validate recipient via Pre-Send Validation API
        $valRes = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->postJson('/api/campaign/validate', [
                'recipient_ids' => [$lead->salesforce_id],
            ]);

        $valRes->assertStatus(200);
        $valRes->assertJson([
            'total_selected' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
        ]);
        $valRes->assertJsonFragment([
            'record_type' => 'Lead',
            'owner_name' => 'Alice Walker',
            'owner_verification_status' => 'verified',
        ]);

        // 3. Dispatch Campaign
        $sendPayload = [
            'subject' => 'Exclusive Q4 Proposal',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'rma@proitbuyer.com',
            'body' => '<p>Hello John, check our new offerings.</p>',
            'recipient_ids' => [$lead->salesforce_id],
        ];

        $sendRes = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->postJson('/api/campaign/send', $sendPayload);

        $sendRes->assertStatus(200);
        $campaignId = $sendRes->json('campaign_id');
        $this->assertNotEmpty($campaignId);

        // 4. Verify CampaignMember is created with proper owner metadata and links
        $member = CampaignMember::where('campaign_id', $campaignId)->first();
        $this->assertNotNull($member);
        $this->assertEquals('Lead', $member->record_type);
        $this->assertEquals($lead->id, $member->local_record_id);
        $this->assertEquals($lead->salesforce_id, $member->salesforce_record_id);
        $this->assertEquals('john.doe@acme.com', $member->email);
        $this->assertEquals('John Doe', $member->name);
        $this->assertEquals('Acme Corporation', $member->company);
        $this->assertEquals($sfUser->salesforce_id, $member->salesforce_owner_id);
        $this->assertEquals('Alice Walker', $member->owner_name);
        $this->assertEquals('alice@salesforce.com', $member->owner_email);
        $this->assertEquals('verified', $member->owner_verification_status);

        // 5. Verify RecipientLog links to CampaignMember and has owner verification status
        $log = RecipientLog::where('campaign_id', $campaignId)->first();
        $this->assertNotNull($log);
        $this->assertEquals($member->id, $log->campaign_member_id);
        $this->assertEquals('verified', $log->owner_verification_status);

        // 6. Test Model Relationships
        $this->assertInstanceOf(SalesforceLead::class, $member->lead);
        $this->assertEquals($lead->id, $member->lead->id);
        $this->assertInstanceOf(Campaign::class, $member->campaign);
        $this->assertEquals(1, $lead->campaignMembers()->count());
    }

    public function test_campaign_dispatch_with_real_salesforce_contacts_and_accounts(): void
    {
        SalesforceUser::firstOrCreate(
            ['salesforce_id' => '005SF0000000001'],
            ['name' => 'Alice Walker', 'username' => 'admin', 'email' => 'admin@b2bexportsllc.com', 'is_active' => true]
        );

        $account = SalesforceAccount::create([
            'salesforce_id' => '001REAL000000001',
            'name' => 'Stark Industries',
            'phone' => '+1 800-STARK',
            'owner_id' => '005SF0000000001',
            'owner_name' => 'Alice Walker',
            'owner_verification_status' => 'verified',
            'last_owner_verified_at' => Carbon::now(),
        ]);

        $contact = SalesforceContact::create([
            'salesforce_id' => '003REAL000000001',
            'account_id' => $account->salesforce_id,
            'first_name' => 'Tony',
            'last_name' => 'Stark',
            'name' => 'Tony Stark',
            'email' => 'tony@stark.com',
            'owner_id' => '005SF0000000001',
            'owner_name' => 'Alice Walker',
            'owner_verification_status' => 'verified',
            'last_owner_verified_at' => Carbon::now(),
        ]);

        // Send campaign to this Contact
        $sendRes = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->postJson('/api/campaign/send', [
                'subject' => 'Stark Initiative',
                'sending_domain' => 'proitbuyer.com',
                'from_address' => 'rma@proitbuyer.com',
                'body' => '<p>Welcome Tony</p>',
                'recipient_ids' => [$contact->salesforce_id],
            ]);

        $sendRes->assertStatus(200);
        $campaignId = $sendRes->json('campaign_id');

        $member = CampaignMember::where('campaign_id', $campaignId)->first();
        $this->assertNotNull($member);
        $this->assertEquals('Contact', $member->record_type);
        $this->assertEquals($contact->id, $member->local_record_id);
        $this->assertEquals('Tony Stark', $member->name);
        $this->assertEquals('Stark Industries', $member->company);
        $this->assertInstanceOf(SalesforceContact::class, $member->contact);
    }

    public function test_sync_service_owner_change_detection(): void
    {
        $syncService = app(SalesforceLeadSyncService::class);

        // Create initial lead
        $lead = SalesforceLead::create([
            'salesforce_id' => '00QTEST000000099',
            'name' => 'Bob Builder',
            'email' => 'bob@build.com',
            'owner_id' => '005ORIGINAL00001',
            'owner_name' => 'Original Owner',
            'owner_verification_status' => 'verified',
            'last_owner_verified_at' => Carbon::now()->subDays(5),
        ]);

        // Call updateSalesforceRecord with a new owner
        $updated = $this->sfService->updateSalesforceRecord('00QTEST000000099', [
            'owner_id' => '005NEWOWNER00002',
        ]);

        $lead->refresh();
        $this->assertEquals('005NEWOWNER00002', $lead->owner_id);
        $this->assertEquals('005ORIGINAL00001', $lead->previous_owner_id);
        $this->assertEquals('changed', $lead->owner_verification_status);
        $this->assertNotNull($lead->last_owner_verified_at);
    }

    public function test_admin_salesforce_leads_directory_search_and_filter(): void
    {
        // Create 2 leads with different verification statuses
        SalesforceLead::create([
            'salesforce_id' => '00QVERIFIED00001',
            'name' => 'Verified Lead',
            'email' => 'verified@lead.com',
            'company' => 'Alpha Inc',
            'owner_verification_status' => 'verified',
            'salesforce_updated_at' => Carbon::now(),
        ]);

        SalesforceLead::create([
            'salesforce_id' => '00QCHANGED000002',
            'name' => 'Changed Lead',
            'email' => 'changed@lead.com',
            'company' => 'Beta Corp',
            'owner_verification_status' => 'changed',
            'previous_owner_id' => '005OLD00001',
            'salesforce_updated_at' => Carbon::now()->subDay(),
        ]);

        // 1. Filter by verification_status = changed
        $res = $this->actingAs($this->admin)
            ->withSession(['user_id' => $this->admin->id, 'role' => 'admin', 'username' => 'admin'])
            ->get('/admin/salesforce-leads?verification_status=changed');

        $res->assertStatus(200);
        $res->assertSee('Changed Lead');
        $res->assertDontSee('Verified Lead');

        // 2. Test show API endpoint
        $lead = SalesforceLead::where('salesforce_id', '00QCHANGED000002')->first();
        $detailRes = $this->actingAs($this->admin)
            ->withSession(['user_id' => $this->admin->id, 'role' => 'admin', 'username' => 'admin'])
            ->getJson('/admin/salesforce-leads/' . $lead->id);

        $detailRes->assertStatus(200);
        $detailRes->assertJsonFragment([
            'owner_verification_status' => 'changed',
            'previous_owner_id' => '005OLD00001',
        ]);
    }

    public function test_different_owner_rejection_for_campaign_members(): void
    {
        // Lead owned by someone else
        $lead = SalesforceLead::create([
            'salesforce_id' => '00QDIFF000000001',
            'name' => 'Foreign Lead',
            'email' => 'foreign@other.com',
            'owner_id' => '005SOMEONEELSE999',
            'owner_name' => 'Someone Else',
            'owner_verification_status' => 'verified',
        ]);

        // Standard user attempts to validate
        $valRes = $this->actingAs($this->standardUser)
            ->withSession(['user_id' => $this->standardUser->id, 'role' => 'user', 'username' => 'user'])
            ->postJson('/api/campaign/validate', [
                'recipient_ids' => [$lead->salesforce_id],
            ]);

        $valRes->assertStatus(200);
        $valRes->assertJson([
            'total_approved' => 0,
            'total_blocked' => 1,
        ]);
        $this->assertCount(1, $valRes->json('blocked_by_reason.DIFFERENT_OWNER'));
    }
}

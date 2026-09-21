<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\RecipientClick;
use App\Models\RecipientLog;
use App\Models\SalesforceMockRecord;
use App\Models\SendingDomain;
use App\Models\User;
use App\Services\CampaignProcessingService;
use App\Services\PabblyService;
use App\Services\SalesforceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkEmailClientTest extends TestCase
{
    use RefreshDatabase;

    protected SalesforceService $sfService;
    protected CampaignProcessingService $processingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->sfService = app(SalesforceService::class);
        $this->processingService = app(CampaignProcessingService::class);
    }

    public function test_database_seeding(): void
    {
        $userCount = User::count();
        $this->assertGreaterThanOrEqual(3, $userCount);

        $domainCount = SendingDomain::count();
        $this->assertGreaterThanOrEqual(1, $domainCount);

        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('admin', $admin->password));
    }

    public function test_salesforce_client_eligibility_checks(): void
    {
        // 1. Check valid recipient owned by user
        // '003SF0000000006' is a Contact owned by 'user', opted_out = 0, status = Active, consent = valid
        [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility('003SF0000000006', 'user');
        $this->assertTrue($isEligible);
        $this->assertNull($reason);

        // 2. Check recipient owned by other user (different owner validation)
        // '003SF0000000001' is owned by 'admin'. Checking as 'user' should block it.
        [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility('003SF0000000001', 'user');
        $this->assertFalse($isEligible);
        $this->assertEquals('DIFFERENT_OWNER', $reason);

        // 3. Check opted out record
        // '003SF0000000003' is owned by 'admin', but opted_out = 1
        [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility('003SF0000000003', 'admin');
        $this->assertFalse($isEligible);
        $this->assertEquals('EMAIL_OPT_OUT', $reason);

        // 4. Check inactive record
        // '003SF0000000004' is inactive
        [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility('003SF0000000004', 'admin');
        $this->assertFalse($isEligible);
        $this->assertEquals('INACTIVE_RECORD', $reason);

        // 5. Check missing consent
        // '003SF0000000005' has consent_status = 'missing'
        [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility('003SF0000000005', 'admin');
        $this->assertFalse($isEligible);
        $this->assertEquals('MISSING_CONSENT', $reason);
    }

    public function test_recipient_splitting_validation_api(): void
    {
        $admin = User::where('username', 'admin')->first();

        $payload = [
            'recipient_ids' => [
                '003SF0000000001',
                '003SF0000000003',
                '003SF0000000006',
                '003SF0000000005',
                '003SF0000000001' // Duplicate
            ]
        ];

        $res = $this->actingAs($admin)
            ->withSession([
                'user_id' => $admin->id,
                'username' => $admin->username,
                'role' => $admin->role
            ])
            ->postJson('/api/campaign/validate', $payload);

        $res->assertStatus(200);
        $data = $res->json();

        $this->assertEquals(5, $data['total_selected']);
        $this->assertEquals(1, $data['total_approved']);
        $this->assertCount(1, $data['duplicates']);
        $this->assertCount(1, $data['blocked_by_reason']['EMAIL_OPT_OUT']);
        $this->assertCount(1, $data['blocked_by_reason']['DIFFERENT_OWNER']);
        $this->assertCount(1, $data['blocked_by_reason']['MISSING_CONSENT']);
    }

    public function test_send_time_revalidation_blocking(): void
    {
        $admin = User::where('username', 'admin')->first();

        $payload = [
            'subject' => 'Send-time test',
            'body' => '<p>Test</p>',
            'sending_domain' => 'marketing.example.com',
            'from_address' => 'admin@marketing.example.com',
            'reply_to' => 'reply@reply.example.net',
            'recipient_ids' => ['003SF0000000002']
        ];

        $res = $this->actingAs($admin)
            ->withSession([
                'user_id' => $admin->id,
                'username' => $admin->username,
                'role' => $admin->role
            ])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(200);
        $campaignId = $res->json('campaign_id');

        $campaign = Campaign::find($campaignId);
        $this->assertEquals('queued', $campaign->status);

        // Simulate Salesforce CDC event changing record owner from admin to user
        $this->sfService->updateSalesforceRecord('003SF0000000002', ['owner_id' => 'user']);

        // Process queue
        $this->processingService->processQueuedEmails();

        $campaign->refresh();
        $this->assertEquals('completed', $campaign->status);
        $this->assertEquals(0, $campaign->total_approved);
        $this->assertEquals(1, $campaign->total_blocked);

        $log = RecipientLog::where('campaign_id', $campaignId)->first();
        $this->assertEquals('blocked', $log->decision);
        $this->assertEquals('DIFFERENT_OWNER', $log->decision_reason);
        $this->assertEquals('blocked', $log->delivery_status);

        // Reset record back
        $this->sfService->updateSalesforceRecord('003SF0000000002', ['owner_id' => 'admin']);
    }

    public function test_validate_emails_api(): void
    {
        $user = User::where('username', 'user')->first();

        $payload = [
            'recipient_emails' => [
                'john.doe@example.com',
                'david.jones@example.com',
                'unsubscribed-lead@gmail.com',
                'non-existent@corp.com'
            ]
        ];

        $res = $this->actingAs($user)
            ->withSession([
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role
            ])
            ->postJson('/api/campaign/validate', $payload);

        $res->assertStatus(200);
        $data = $res->json();

        $this->assertEquals(4, $data['total_selected']);
        $this->assertEquals(1, $data['total_approved']);
        $this->assertCount(1, $data['blocked_by_reason']['DIFFERENT_OWNER']);
        $this->assertCount(1, $data['blocked_by_reason']['GLOBAL_SUPPRESSION']);
        $this->assertCount(1, $data['blocked_by_reason']['INVALID_EMAIL']);
    }

    public function test_send_with_emails_and_attachments(): void
    {
        $user = User::where('username', 'user')->first();

        $payload = [
            'subject' => 'Attachments and emails send test',
            'body' => '<p>Body</p>',
            'sending_domain' => 'sales.example.com',
            'from_address' => 'user@sales.example.com',
            'reply_to' => 'reply@reply.example.net',
            'recipient_emails' => ['david.jones@example.com'],
            'attachments' => ['brochure_q3.pdf', 'pricing_matrix.xlsx']
        ];

        $res = $this->actingAs($user)
            ->withSession([
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role
            ])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(200);
        $campaignId = $res->json('campaign_id');

        $campaign = Campaign::find($campaignId);
        $this->assertStringContainsString('brochure_q3.pdf', $campaign->attachments);
        $this->assertStringContainsString('pricing_matrix.xlsx', $campaign->attachments);
    }

    public function test_click_tracking(): void
    {
        $admin = User::where('username', 'admin')->first();
        $token = 'test-click-token-999';

        $campaign = Campaign::create([
            'id' => 'dummy-camp-001',
            'subject' => 'Test',
            'body' => 'Test',
            'sending_domain' => 'domain.com',
            'from_address' => 'from@domain.com',
            'reply_to' => 'reply@domain.com',
            'user_id' => $admin->id
        ]);

        RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'recipient@domain.com',
            'salesforce_record_id' => 'sf-123',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'tracking_token' => $token
        ]);

        $res = $this->get("/track/click/{$token}?url=https://www.google.com");
        $res->assertStatus(302);
        $res->assertRedirect('https://www.google.com');

        $this->assertEquals(1, RecipientClick::where('url', 'https://www.google.com')->count());
    }

    public function test_scheduling_validation(): void
    {
        $user = User::where('username', 'user')->first();

        // 1. Test schedule > 24 hours (should fail)
        $invalidTime = now()->addHours(25)->format('Y-m-d\TH:i');
        $payload = [
            'subject' => 'Scheduled too far',
            'body' => '<p>Body</p>',
            'sending_domain' => 'sales.example.com',
            'from_address' => 'user@sales.example.com',
            'reply_to' => 'reply@reply.example.net',
            'recipient_emails' => ['david.jones@example.com'],
            'scheduled_at' => $invalidTime
        ];

        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(400);
        $this->assertStringContainsString('within 24 hours', $res->json('error'));

        // 2. Test schedule in past (should fail)
        $pastTime = now()->subMinutes(10)->format('Y-m-d\TH:i');
        $payload['scheduled_at'] = $pastTime;

        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(400);
        $this->assertStringContainsString('in the future', $res->json('error'));

        // 3. Test schedule valid (next 2 hours - should pass)
        $validTime = now()->addHours(2)->format('Y-m-d\TH:i');
        $payload['scheduled_at'] = $validTime;

        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(200);
    }

    public function test_manager_assignment_and_campaign_approval(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Create Manager
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/users', [
                'emp_id' => 'EMP-T01',
                'username' => 'test_mngr',
                'email' => 'mngr@test.com',
                'password' => 'mngr_password',
                'role' => 'manager'
            ]);
        $res->assertStatus(200);

        $manager = User::where('username', 'test_mngr')->first();
        $this->assertNotNull($manager);

        // Create User assigned to Manager
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/users', [
                'emp_id' => 'EMP-T02',
                'username' => 'test_usr',
                'email' => 'usr@test.com',
                'password' => 'usr_password',
                'role' => 'user',
                'manager_id' => $manager->id
            ]);
        $res->assertStatus(200);

        $testUser = User::where('username', 'test_usr')->first();
        $this->assertNotNull($testUser);

        // 2. Submit campaign as User
        $res = $this->actingAs($testUser)
            ->withSession(['user_id' => $testUser->id, 'username' => $testUser->username, 'role' => 'user'])
            ->postJson('/api/campaign/send', [
                'subject' => 'Approval Flow Test',
                'body' => '<p>Content to approve</p>',
                'sending_domain' => 'marketing.example.com',
                'from_address' => 'test_usr@marketing.example.com',
                'reply_to' => 'reply@example.com',
                'recipient_emails' => ['david.jones@example.com']
            ]);
        $res->assertStatus(200);
        $campaignId = $res->json('campaign_id');

        $campaign = Campaign::find($campaignId);
        $this->assertEquals('pending_approval', $campaign->status);

        // 3. Try approving logged in as another manager
        $otherManager = User::where('username', 'manager')->first();
        $res = $this->actingAs($otherManager)
            ->withSession(['user_id' => $otherManager->id, 'username' => $otherManager->username, 'role' => 'manager'])
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaignId,
                'decision' => 'approve',
                'remark' => 'Hack approval'
            ]);
        $res->assertStatus(403);

        // 4. Log in as assigned manager and approve
        $res = $this->actingAs($manager)
            ->withSession(['user_id' => $manager->id, 'username' => $manager->username, 'role' => 'manager'])
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaignId,
                'decision' => 'approve',
                'remark' => 'Approved for dispatch'
            ]);
        $res->assertStatus(200);

        $campaign->refresh();
        $this->assertEquals('queued', $campaign->status);
        $this->assertEquals('Approved for dispatch', $campaign->approval_remark);
        $this->assertEquals($manager->id, $campaign->approved_by);
    }

    public function test_email_login_edit_and_blocking(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Create user
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/users', [
                'emp_id' => 'EMP-T99',
                'username' => 'block_test',
                'email' => 'block@test.com',
                'password' => 'password123',
                'role' => 'manager'
            ]);
        $res->assertStatus(200);

        $blockUser = User::where('username', 'block_test')->first();
        $this->assertNotNull($blockUser);

        // 2. Verify login
        $res = $this->post('/login', [
            'email' => 'block@test.com',
            'password' => 'password123'
        ]);
        $res->assertStatus(302);
        $res->assertRedirect('/');

        // 3. Admin blocks user
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->putJson('/api/admin/users', [
                'id' => $blockUser->id,
                'emp_id' => 'EMP-T99',
                'username' => 'block_test',
                'email' => 'block@test.com',
                'role' => 'user',
                'manager_id' => 2,
                'is_blocked' => 1
            ]);
        $res->assertStatus(200);

        // 4. Attempt login as blocked user
        $res = $this->post('/login', [
            'email' => 'block@test.com',
            'password' => 'password123'
        ]);
        $res->assertSessionHas('danger', 'Your account has been blocked. Please contact the administrator.');

        // 5. Admin unblocks user
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->putJson('/api/admin/users', [
                'id' => $blockUser->id,
                'emp_id' => 'EMP-T99',
                'username' => 'block_test',
                'email' => 'block@test.com',
                'role' => 'user',
                'manager_id' => 2,
                'is_blocked' => 0
            ]);
        $res->assertStatus(200);

        // 6. Verify successful login
        $res = $this->post('/login', [
            'email' => 'block@test.com',
            'password' => 'password123'
        ]);
        $res->assertStatus(302);
        $res->assertRedirect('/');
    }

    public function test_inbound_replies_and_linking(): void
    {
        $user = User::where('username', 'user')->first();

        $campaign = Campaign::create([
            'id' => 'dummy-camp-999',
            'subject' => 'Product Demo',
            'body' => 'Hello',
            'sending_domain' => 'sales.example.com',
            'from_address' => 'user@sales.example.com',
            'reply_to' => 'reply@reply.example.net',
            'user_id' => $user->id
        ]);

        // 1. Dispatch inbound reply
        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->postJson('/api/simulator/inbound-reply', [
                'campaign_id' => $campaign->id,
                'email' => 'david.jones@example.com',
                'subject' => 'Re: Product Demo',
                'body' => 'I would love to schedule a demo next Tuesday.'
            ]);

        $res->assertStatus(200);
        $this->assertTrue($res->json('success'));

        // 2. Query replies API
        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->getJson('/api/replies');

        $res->assertStatus(200);
        $replies = $res->json();
        $this->assertGreaterThanOrEqual(1, count($replies));

        $found = collect($replies)->firstWhere('recipient_email', 'david.jones@example.com');
        $this->assertNotNull($found);
        $this->assertEquals('user', $found['mapped_owner_id']);
        $this->assertEquals('003SF0000000006', $found['mapped_salesforce_record_id']);
    }

    public function test_provider_webhook_and_tracking_open_unsubscribe(): void
    {
        $admin = User::where('username', 'admin')->first();

        $campaign = Campaign::create([
            'id' => 'camp-track-001',
            'subject' => 'Tracking Test',
            'body' => 'Hello {{FirstName}}, <p>Unsubscribe <a href="{{UnsubscribeLink}}">here</a></p>',
            'sending_domain' => 'marketing.example.com',
            'from_address' => 'admin@marketing.example.com',
            'reply_to' => 'reply@marketing.example.com',
            'user_id' => $admin->id
        ]);

        $log = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'trackme@example.com',
            'salesforce_record_id' => '003SF0000000001',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'provider_message_id' => 'msg-prov-999',
            'tracking_token' => 'token-track-abc'
        ]);

        // 1. Simulate Open tracking
        $res = $this->get('/track/open/token-track-abc?country=US&region=California&city=San Francisco');
        $res->assertStatus(200);
        $this->assertEquals('image/gif', $res->headers->get('Content-Type'));

        $log->refresh();
        $this->assertEquals('opened', $log->delivery_status);

        // 2. Simulate Webhook (e.g. delivered)
        $res = $this->postJson('/api/simulator/trigger-webhook', [
            'provider_message_id' => 'msg-prov-999',
            'event' => 'delivered'
        ]);
        $res->assertStatus(200);
        $log->refresh();
        $this->assertEquals('delivered', $log->delivery_status);

        // 3. Simulate Unsubscribe
        $res = $this->get('/track/unsubscribe/token-track-abc');
        $res->assertStatus(200); // Unsubscribe confirmation form

        $res = $this->post('/track/unsubscribe/token-track-abc');
        $res->assertStatus(200);
        $log->refresh();
        $this->assertEquals('unsubscribed', $log->delivery_status);

        $this->assertDatabaseHas('global_suppression', [
            'email' => 'trackme@example.com'
        ]);
    }

    public function test_admin_infrastructure_crud(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Create Server
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/servers', [
                'name' => 'Test Cluster EU',
                'host' => 'smtp.eu-test.net',
                'port' => 587,
                'username' => 'apikey',
                'password' => 'secret123',
                'sending_ip' => '192.168.10.99',
                'is_active' => 1
            ]);
        $res->assertStatus(200);
        $serverId = $res->json('server_id');

        // 2. Create Domain
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/domains', [
                'domain_name' => 'eu-outreach.example.com',
                'server_id' => $serverId,
                'rate_limit_per_hour' => 5000,
                'is_default' => 0,
                'spf_status' => 'verified',
                'dkim_status' => 'verified',
                'dmarc_status' => 'verified',
                'status' => 'enabled'
            ]);
        $res->assertStatus(200);
        $domainId = $res->json('domain_id');

        // 3. Save CRM settings
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/crm-settings', [
                'salesforce_client_id' => 'SF_KEY_123',
                'salesforce_client_secret' => 'SF_SEC_456',
                'salesforce_login_url' => 'https://login.salesforce.com',
                'salesforce_username' => 'crm-admin@domain.com',
                'salesforce_token_or_password' => 'pwd_token',
                'is_mock' => 1
            ]);
        $res->assertStatus(200);

        // 4. Delete Domain and Server
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->deleteJson("/api/admin/domains?id={$domainId}");
        $res->assertStatus(200);

        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->deleteJson("/api/admin/servers?id={$serverId}");
        $res->assertStatus(200);
    }

    public function test_templates_and_signatures_management(): void
    {
        $user = User::where('username', 'user')->first();

        // 1. Create Signature
        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->postJson('/api/signatures', [
                'name' => 'Sales Signature',
                'content' => '<p>Best regards,<br><strong>User</strong></p>',
                'is_default' => 1
            ]);
        $res->assertStatus(200);
        $sigId = $res->json('signature_id');

        // 2. Create Template
        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->postJson('/api/templates', [
                'name' => 'Demo Follow-up',
                'subject' => 'Follow up on our discussion',
                'body' => '<p>Hi {{FirstName}}, following up on our demo.</p>'
            ]);
        $res->assertStatus(200);
        $tmplId = $res->json('template_id');

        // 3. Fetch Assigned Settings
        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->getJson('/api/user/assigned-settings');
        $res->assertStatus(200);
        $data = $res->json();
        $this->assertArrayHasKey('domain_name', $data);
        $this->assertArrayHasKey('assigned_domain_id', $data);

        // 4. Delete Template and Signature
        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->deleteJson("/api/templates/{$tmplId}");
        $res->assertStatus(200);

        $res = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role])
            ->deleteJson("/api/signatures/{$sigId}");
        $res->assertStatus(200);
    }

    public function test_dashboard_stats_and_recipient_list(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Dashboard stats
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->getJson('/api/dashboard/stats');
        $res->assertStatus(200);
        $data = $res->json();
        $this->assertArrayHasKey('counters', $data);
        $this->assertArrayHasKey('geo_data', $data);
        $this->assertArrayHasKey('timeline_data', $data);
        $this->assertArrayHasKey('recent_campaigns', $data);

        // 2. Dashboard recipient list drilldown
        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->getJson('/api/dashboard/recipient-list?status=total');
        $res->assertStatus(200);
        $recipients = $res->json();
        $this->assertIsArray($recipients);
    }

    public function test_all_blade_views_render_successfully(): void
    {
        $admin = User::where('username', 'admin')->first();
        $campaign = Campaign::first() ?: Campaign::create([
            'id' => 'test-camp-render',
            'subject' => 'Test Subject',
            'body' => 'Test Body',
            'sending_domain' => 'marketing.example.com',
            'from_address' => 'admin@marketing.example.com',
            'user_id' => $admin->id,
        ]);

        $routes = [
            '/',
            '/campaign/new',
            '/campaign/create',
            '/campaign/list',
            "/campaign/{$campaign->id}",
            '/signatures',
            '/manager/campaigns',
            '/replies',
            '/admin/users',
            '/admin/infrastructure',
            '/simulator',
            '/campaign/bulk',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)
                ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
                ->get($route);
            $response->assertStatus(200);
        }

        // Test unauthenticated login page
        $this->app['auth']->guard()->logout();
        session()->flush();
        $res = $this->get('/login');
        $res->assertStatus(200);
    }

    public function test_bulk_email_route_and_sidebar_rendering(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Visit /campaign/bulk (Bulk Email view)
        $resBulk = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/campaign/bulk');

        $resBulk->assertStatus(200);
        $resBulk->assertSee('Bulk Email');
        $resBulk->assertSee('Paste Raw List');
        $resBulk->assertSee('pasted-emails');
        $resBulk->assertSee('pasted-email-counter');

        // 2. Visit /campaign/new (CRM Directory view)
        $resNew = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/campaign/new');

        $resNew->assertStatus(200);
        $resNew->assertSee('Create Campaign');
        $resNew->assertSee('CRM Directory');
    }

    public function test_campaign_creation_and_bulk_views_contain_reply_to_field(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Check /campaign/new
        $resNew = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/campaign/new');
        $resNew->assertStatus(200);
        $resNew->assertSee('Reply-To Email');
        $resNew->assertSee('support@b2bexportsllc.com');
        $resNew->assertSee('id="reply-to"', false);

        // 2. Check /campaign/bulk
        $resBulk = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/campaign/bulk');
        $resBulk->assertStatus(200);
        $resBulk->assertSee('Reply-To Email');
        $resBulk->assertSee('support@b2bexportsllc.com');
        $resBulk->assertSee('id="reply-to"', false);
    }

    public function test_campaign_creation_persists_default_reply_to_support_email(): void
    {
        $admin = User::where('username', 'admin')->first();

        $payload = [
            'subject' => 'Test Default Reply-To Support',
            'body' => 'Hello from bulk email testing',
            'sending_domain' => 'domain.com',
            'from_address' => 'rma@proitbuyer.com',
            // reply_to omitted to verify default
            'recipient_emails' => ['lead1@proitbuyer.com'],
        ];

        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $campaignId = $res->json('campaign_id');
        $campaign = Campaign::find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertEquals('support@b2bexportsllc.com', $campaign->reply_to);
    }

    public function test_campaign_creation_persists_custom_reply_to(): void
    {
        $admin = User::where('username', 'admin')->first();

        $payload = [
            'subject' => 'Test Custom Reply-To',
            'body' => 'Hello with custom reply to',
            'sending_domain' => 'domain.com',
            'from_address' => 'rma@proitbuyer.com',
            'reply_to' => 'custom-replies@example.com',
            'recipient_emails' => ['lead1@proitbuyer.com'],
        ];

        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/campaign/send', $payload);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $campaignId = $res->json('campaign_id');
        $campaign = Campaign::find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertEquals('custom-replies@example.com', $campaign->reply_to);
    }

    public function test_pabbly_service_payload_includes_reply_to(): void
    {
        config(['pabbly.api_key' => 'test_api_key']);
        config(['pabbly.reply_to' => 'support@b2bexportsllc.com']);

        Http::fake([
            'https://emails.pabbly.com/api/v2/campaigns' => Http::response([
                'status' => 'success',
                'data' => ['_id' => 'pabbly_camp_123'],
            ], 200),
            'https://emails.pabbly.com/api/v2/campaigns/send-to-individual' => Http::response([
                'status' => 'success',
                'data' => ['queued' => 1],
            ], 200),
        ]);

        $pabbly = new PabblyService();
        $result = $pabbly->sendEmail(
            'customer@example.com',
            'Customer Name',
            'Test Subject',
            '<p>Test Body</p>',
            'sender@proitbuyer.com',
            'Sender Name',
            'send-with-us',
            'support@b2bexportsllc.com'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('pabbly_camp_123', $result['pabbly_campaign_id']);

        // Verify POST /campaigns received replyToEmail & replyTo
        Http::assertSent(function ($request) {
            if ($request->url() === 'https://emails.pabbly.com/api/v2/campaigns') {
                $details = $request['campaignDetails'] ?? [];
                return ($details['replyToEmail'] ?? null) === 'support@b2bexportsllc.com'
                    && ($details['replyTo'] ?? null) === 'support@b2bexportsllc.com';
            }
            return true;
        });

        // Verify POST /campaigns/send-to-individual received replyToEmail & replyTo
        Http::assertSent(function ($request) {
            if ($request->url() === 'https://emails.pabbly.com/api/v2/campaigns/send-to-individual') {
                return ($request['replyToEmail'] ?? null) === 'support@b2bexportsllc.com'
                    && ($request['replyTo'] ?? null) === 'support@b2bexportsllc.com';
            }
            return true;
        });
    }
}


<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\GlobalSuppression;
use App\Models\RecipientLog;
use App\Models\RecipientOpen;
use App\Models\SalesforceMockRecord;
use App\Models\SendingDomain;
use App\Models\User;
use App\Services\CampaignProcessingService;
use App\Services\PabblyService;
use App\Services\SalesforceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PabblyWebhookUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookToken = 'test-secret-token-12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Config::set('pabbly.webhook_token', $this->webhookToken);
    }

    /**
     * Test security: Webhook rejects request when token is invalid or missing.
     */
    public function test_webhook_rejects_unauthorized_token(): void
    {
        $payload = [
            'event_type' => 'email_unsubscribed',
            'data' => [
                'subscriber' => ['email' => 'unsub@example.com'],
            ],
        ];

        // Missing token
        $response = $this->postJson('/api/webhooks/pabbly', $payload);
        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorized token.']);

        // Invalid token
        $response = $this->postJson('/api/webhooks/pabbly?token=wrong-token', $payload);
        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorized token.']);
    }

    /**
     * Test security: Webhook accepts valid token via query string, X-Pabbly-Token header, or Bearer token.
     */
    public function test_webhook_accepts_valid_token_formats(): void
    {
        $payload = [
            'event_type' => 'email_unsubscribed',
            'data' => [
                'subscriber' => ['email' => 'header-test@example.com'],
            ],
        ];

        // Via query parameter
        $resQuery = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $payload);
        $resQuery->assertStatus(200)->assertJson(['success' => true]);

        // Via X-Pabbly-Token header
        $resHeader = $this->withHeaders(['X-Pabbly-Token' => $this->webhookToken])
            ->postJson('/api/webhooks/pabbly', [
                'event_type' => 'email_unsubscribed',
                'data' => ['subscriber' => ['email' => 'header2@example.com']],
            ]);
        $resHeader->assertStatus(200)->assertJson(['success' => true]);

        // Via Bearer token
        $resBearer = $this->withToken($this->webhookToken)
            ->postJson('/api/webhooks/pabbly', [
                'event_type' => 'email_unsubscribed',
                'data' => ['subscriber' => ['email' => 'bearer@example.com']],
            ]);
        $resBearer->assertStatus(200)->assertJson(['success' => true]);
    }

    /**
     * Test Unsubscribe event processing: records suppression and updates logs and Salesforce.
     */
    public function test_unsubscribe_event_records_suppression_and_updates_status(): void
    {
        $targetEmail = 'jane.doe@example.com';

        // 1. Create a mock Salesforce record
        $sfRecord = SalesforceMockRecord::create([
            'id' => '003SF' . strtoupper(Str::random(10)),
            'object_type' => 'Contact',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $targetEmail,
            'owner_id' => 'admin',
            'opted_out' => false,
            'status' => 'Active',
            'consent_status' => 'valid',
            'lawful_basis' => 'Consent',
        ]);

        // 2. Create a Campaign and RecipientLog
        $admin = User::where('username', 'admin')->first();
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Test Campaign',
            'body' => 'Hello Jane',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'rma@proitbuyer.com',
            'reply_to' => 'support@b2bexportsllc.com',
            'user_id' => $admin->id,
            'status' => 'completed',
        ]);

        $recipientLog = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => $targetEmail,
            'salesforce_record_id' => $sfRecord->id,
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'provider_message_id' => 'pabbly-msg-999',
            'tracking_token' => Str::random(32),
        ]);

        // 3. Post Pabbly unsubscribe webhook with uppercase email to test normalization
        $payload = [
            'event_type' => 'email_unsubscribed',
            'data' => [
                'subscriber' => [
                    'id' => 'sub_12345',
                    'email' => 'JANE.DOE@EXAMPLE.COM',
                ],
                'campaign_id' => $campaign->id,
                'message_id' => 'pabbly-msg-999',
            ],
        ];

        $response = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $payload);
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'event' => 'unsubscribed',
                     'email' => 'jane.doe@example.com',
                 ]);

        // 4. Verify GlobalSuppression entry created with normalized email
        $this->assertTrue(GlobalSuppression::isSuppressed('jane.doe@example.com'));
        $this->assertTrue(GlobalSuppression::isSuppressed('JANE.DOE@EXAMPLE.COM'));
        $suppression = GlobalSuppression::where('email', 'jane.doe@example.com')->first();
        $this->assertNotNull($suppression);
        $this->assertEquals('pabbly_webhook', $suppression->source);
        $this->assertEquals($campaign->id, $suppression->campaign_id);
        $this->assertEquals('pabbly-msg-999', $suppression->provider_message_id);

        // 5. Verify RecipientLog delivery_status updated
        $recipientLog->refresh();
        $this->assertEquals('unsubscribed', $recipientLog->delivery_status);

        // 6. Verify Salesforce Mock Record marked opted_out = true
        $sfRecord->refresh();
        $this->assertTrue((bool) $sfRecord->opted_out);
    }

    /**
     * Test Idempotency: Duplicate webhook delivery does not fail or duplicate suppression rows.
     */
    public function test_unsubscribe_webhook_is_idempotent(): void
    {
        $payload = [
            'event_type' => 'email_unsubscribed',
            'data' => [
                'subscriber' => ['email' => 'idempotent@example.com'],
                'campaign_id' => 'camp-idem-1',
            ],
        ];

        // First delivery
        $res1 = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $payload);
        $res1->assertStatus(200);

        // Second duplicate delivery
        $res2 = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $payload);
        $res2->assertStatus(200);

        // Verify only 1 suppression record exists
        $count = GlobalSuppression::where('email', 'idempotent@example.com')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test Delivery and Open events through the same webhook endpoint.
     */
    public function test_delivery_and_open_events_through_webhook(): void
    {
        $admin = User::where('username', 'admin')->first();
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Delivery Test',
            'body' => 'Body',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'rma@proitbuyer.com',
            'reply_to' => 'support@b2bexportsllc.com',
            'user_id' => $admin->id,
            'status' => 'sending',
        ]);

        $recipientLog = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'delivery.test@example.com',
            'salesforce_record_id' => '003SF_MOCK_1',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'provider_message_id' => 'msg-deliv-101',
            'tracking_token' => Str::random(32),
        ]);

        // 1. Post Delivery Event
        $deliveryPayload = [
            'event_type' => 'email_delivered',
            'data' => [
                'email' => 'delivery.test@example.com',
                'message_id' => 'msg-deliv-101',
            ],
        ];

        $resDeliv = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $deliveryPayload);
        $resDeliv->assertStatus(200)->assertJson(['event' => 'delivered']);

        $recipientLog->refresh();
        $this->assertEquals('delivered', $recipientLog->delivery_status);

        // 2. Post Open Event
        $openPayload = [
            'event_type' => 'email_opened',
            'data' => [
                'email' => 'delivery.test@example.com',
                'message_id' => 'msg-deliv-101',
            ],
        ];

        $resOpen = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $openPayload);
        $resOpen->assertStatus(200)->assertJson(['event' => 'opened']);

        $recipientLog->refresh();
        $this->assertEquals('opened', $recipientLog->delivery_status);
        $this->assertDatabaseHas('recipient_opens', [
            'recipient_log_id' => $recipientLog->id,
        ]);
    }

    /**
     * Test Campaign Pre-Send Validation excludes suppressed emails (both CRM and CSV/raw).
     */
    public function test_campaign_validation_blocks_suppressed_emails(): void
    {
        $admin = User::where('username', 'admin')->first();

        // Add email to global suppression
        GlobalSuppression::recordUnsubscribe('blocked.user@example.com');

        // Create an eligible Contact in Salesforce with the same email
        $sfContact = SalesforceMockRecord::create([
            'id' => '003SF' . strtoupper(Str::random(10)),
            'object_type' => 'Contact',
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'email' => 'blocked.user@example.com',
            'owner_id' => 'admin',
            'opted_out' => false,
            'status' => 'Active',
            'consent_status' => 'valid',
        ]);

        // Validate via apiValidate
        $response = $this->actingAs($admin)
            ->withSession([
                'user_id' => $admin->id,
                'username' => $admin->username,
                'role' => $admin->role,
            ])
            ->postJson('/api/campaign/validate', [
                'recipient_ids' => [$sfContact->id],
                'recipient_emails' => ['BLOCKED.USER@EXAMPLE.COM'], // Upper case to test CSV / raw entry
            ]);

        $response->assertStatus(200);
        $data = $response->json();

        // Assert that both entries are recognized as blocked under GLOBAL_SUPPRESSION
        $this->assertEquals(0, $data['total_approved']);
        $this->assertGreaterThanOrEqual(1, count($data['blocked_by_reason']['GLOBAL_SUPPRESSION']));
    }

    /**
     * Test Send-Time Revalidation blocks recipient if unsubscribed after scheduling.
     */
    public function test_send_time_revalidation_blocks_suppressed_recipient(): void
    {
        $admin = User::where('username', 'admin')->first();

        $sfContact = SalesforceMockRecord::create([
            'id' => '003SF' . strtoupper(Str::random(10)),
            'object_type' => 'Contact',
            'first_name' => 'SendTime',
            'last_name' => 'Test',
            'email' => 'sendtime.test@example.com',
            'owner_id' => 'admin',
            'opted_out' => false,
            'status' => 'Active',
            'consent_status' => 'valid',
        ]);

        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Send-time test',
            'body' => 'Body text',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'rma@proitbuyer.com',
            'reply_to' => 'support@b2bexportsllc.com',
            'user_id' => $admin->id,
            'total_approved' => 1,
            'total_blocked' => 0,
            'status' => 'queued',
        ]);

        $log = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'sendtime.test@example.com',
            'salesforce_record_id' => $sfContact->id,
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'queued',
            'tracking_token' => Str::random(32),
        ]);

        // Simulate that before processing starts, recipient unsubscribes!
        GlobalSuppression::recordUnsubscribe('sendtime.test@example.com');

        // Execute campaign processor
        $processor = app(CampaignProcessingService::class);
        $processed = $processor->processQueuedEmails();

        // 0 should be sent
        $this->assertEquals(0, $processed);

        // Recipient log must be marked blocked with reason GLOBAL_SUPPRESSION
        $log->refresh();
        $this->assertEquals('blocked', $log->decision);
        $this->assertEquals('GLOBAL_SUPPRESSION', $log->decision_reason);
        $this->assertEquals('blocked', $log->delivery_status);
    }
}

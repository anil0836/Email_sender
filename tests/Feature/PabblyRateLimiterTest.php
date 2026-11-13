<?php

namespace Tests\Feature;

use App\Exceptions\PabblyApiException;
use App\Exceptions\PabblyRateLimitException;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\RecipientLog;
use App\Models\User;
use App\Services\CampaignProcessingService;
use App\Services\PabblyRateLimiterService;
use App\Services\PabblyService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PabblyRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Config::set('pabbly.api_key', 'pem_test_api_key_12345');
        Config::set('pabbly.rate_limit_enabled', true);
        Config::set('pabbly.dispatch_interval_seconds', 1);
        Config::set('pabbly.max_retry_attempts', 3);
        Config::set('pabbly.retry_base_delay_seconds', 10);
        Config::set('pabbly.retry_max_delay_seconds', 60);
        Config::set('pabbly.force_pabbly_in_testing', true);

        // Clear rate limiter cache state
        app(PabblyRateLimiterService::class)->clearState('pem_test_api_key_12345');
    }

    /**
     * Helper to create an approved test campaign with recipient logs.
     */
    protected function createApprovedCampaignWithRecipients(int $count = 2): array
    {
        $user = User::where('username', 'admin')->first() ?: User::factory()->create(['role' => 'admin']);

        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Test Rate Limit Campaign ' . Str::random(5),
            'body' => '<p>Hello {{name}}</p>',
            'sending_domain' => 'b2bexportsmail.com',
            'from_address' => 'contact@b2bexportsmail.com',
            'reply_to' => 'support@b2bexportsmail.com',
            'status' => 'queued',
            'total_requested' => $count,
            'total_approved' => $count,
            'total_blocked' => 0,
            'approved_by' => $user->id,
            'approval_at' => Carbon::now(),
        ]);

        $recipients = [];
        for ($i = 1; $i <= $count; $i++) {
            $member = CampaignMember::create([
                'campaign_id' => $campaign->id,
                'record_type' => 'Lead',
                'salesforce_record_id' => '00QTEST' . Str::random(10),
                'email' => "recipient{$i}_{$campaign->id}@example.com",
                'name' => "Recipient {$i}",
                'status' => 'queued',
            ]);

            $recipientLog = RecipientLog::create([
                'campaign_id' => $campaign->id,
                'campaign_member_id' => $member->id,
                'email' => $member->email,
                'salesforce_record_id' => $member->salesforce_record_id,
                'salesforce_object' => 'Raw',
                'record_owner_id' => '005TEST',
                'decision' => 'approved',
                'delivery_status' => 'queued',
                'retry_count' => 0,
                'tracking_token' => (string) Str::uuid(),
            ]);

            $recipients[] = $recipientLog;
        }

        return [$campaign, $recipients];
    }

    /**
     * TEST CASE A: Minimum dispatch interval is enforced.
     */
    public function test_case_a_minimum_interval_is_enforced(): void
    {
        $limiter = app(PabblyRateLimiterService::class);
        $apiKey = 'pem_test_api_key_12345';
        $limiter->clearState($apiKey);

        // Slot 1: immediate (interval = 1s)
        $t1 = microtime(true);
        $slot1 = $limiter->awaitDispatchSlot($apiKey, customInterval: 1);
        $this->assertTrue($slot1);

        // Slot 2: must pause ~1.0 second to satisfy 1s interval
        $slot2 = $limiter->awaitDispatchSlot($apiKey, customInterval: 1);
        $t2 = microtime(true);
        $this->assertTrue($slot2);

        $elapsed = $t2 - $t1;
        $this->assertGreaterThanOrEqual(0.85, $elapsed, "Expected elapsed time to be at least 0.85s for 1s interval, got {$elapsed}s");
    }

    /**
     * TEST CASE B: Concurrent campaigns share the same global rate limiter for the same Pabbly account.
     */
    public function test_case_b_concurrent_campaigns_share_global_limiter(): void
    {
        $limiter = app(PabblyRateLimiterService::class);
        $apiKey = 'pem_shared_key_abc';
        $limiter->clearState($apiKey);

        // Simulate Campaign A reserving slot
        $t1 = microtime(true);
        $slotA = $limiter->awaitDispatchSlot($apiKey, customInterval: 1);
        $this->assertTrue($slotA);

        // Simulate Campaign B requesting slot immediately on same account
        $slotB = $limiter->awaitDispatchSlot($apiKey, customInterval: 1);
        $t2 = microtime(true);
        $this->assertTrue($slotB);

        $elapsed = $t2 - $t1;
        $this->assertGreaterThanOrEqual(0.85, $elapsed, "Expected Campaign B to wait for Campaign A's interval, got {$elapsed}s");
    }

    /**
     * TEST CASE C: HTTP 429 response puts recipient in retry state and initiates cooldown.
     */
    public function test_case_c_http_429_triggers_intelligent_requeue_and_cooldown(): void
    {
        Http::fake([
            'https://emails.pabbly.com/api/v2/campaigns' => Http::response([
                'status' => 'success',
                'data' => ['_id' => 'pabbly_camp_12345'],
            ], 200),
            'https://emails.pabbly.com/api/v2/campaigns/send-to-individual' => Http::response([
                'status' => 'error',
                'message' => 'Too many requests from this IP or user, please try again later.',
            ], 429, ['Retry-After' => '45']),
        ]);

        [$campaign, $recipients] = $this->createApprovedCampaignWithRecipients(1);
        $recipient = $recipients[0];

        $processingService = app(CampaignProcessingService::class);
        $processed = $processingService->processQueuedEmails();

        $this->assertEquals(0, $processed, 'No email should be counted as dispatched when 429 is returned');

        $recipient->refresh();
        $this->assertEquals('queued', $recipient->delivery_status, 'Recipient must remain queued for retry');
        $this->assertEquals(1, $recipient->retry_count, 'Retry count must increment to 1');
        $this->assertNotNull($recipient->next_retry_at, 'next_retry_at must be populated');
        $this->assertTrue($recipient->next_retry_at->isFuture(), 'next_retry_at must be in the future');
        $this->assertStringContainsString('Rate limit hit (HTTP 429)', $recipient->error_message);
        $this->assertEquals('pabbly_camp_12345', $recipient->provider_message_id, 'Created Pabbly campaign ID must be preserved for retry');

        // Check that CampaignMember also stays queued
        $member = CampaignMember::find($recipient->campaign_member_id);
        $this->assertEquals('queued', $member->status);

        // Check that account entered cooldown
        $limiter = app(PabblyRateLimiterService::class);
        $this->assertTrue($limiter->isInCooldown(config('pabbly.api_key')));
    }

    /**
     * TEST CASE D: Permanent non-retryable error marks recipient failed immediately.
     */
    public function test_case_d_permanent_failure_marks_recipient_failed_without_retry(): void
    {
        Http::fake([
            'https://emails.pabbly.com/api/v2/campaigns' => Http::response([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid API key',
            ], 401),
        ]);

        [$campaign, $recipients] = $this->createApprovedCampaignWithRecipients(1);
        $recipient = $recipients[0];

        $processingService = app(CampaignProcessingService::class);
        $processed = $processingService->processQueuedEmails();

        $this->assertEquals(0, $processed);

        $recipient->refresh();
        $this->assertEquals('failed', $recipient->delivery_status, 'Recipient must be marked failed on permanent error');
        $this->assertEquals(0, $recipient->retry_count, 'Retry count should not increment on permanent non-retryable failure');
        $this->assertNull($recipient->next_retry_at, 'next_retry_at must be null');
        $this->assertStringContainsString('Unauthorized', $recipient->error_message);

        $member = CampaignMember::find($recipient->campaign_member_id);
        $this->assertEquals('failed', $member->status);
    }

    /**
     * TEST CASE E: Worker restart leaves pending recipients intact in database.
     */
    public function test_case_e_worker_restart_preserves_pending_recipients(): void
    {
        [$campaign, $recipients] = $this->createApprovedCampaignWithRecipients(2);

        // Set recipient 1 in retry wait
        $recipients[0]->update([
            'delivery_status' => 'queued',
            'retry_count' => 1,
            'next_retry_at' => Carbon::now()->addMinutes(10),
            'error_message' => 'Rate limit hit, waiting for retry',
        ]);

        // "Restart worker" by fetching fresh instances
        $pending = RecipientLog::where('campaign_id', $campaign->id)
            ->where('delivery_status', 'queued')
            ->get();

        $this->assertCount(2, $pending, 'Both recipients must still be queued and preserved');
        $this->assertEquals(1, $pending[0]->retry_count);
        $this->assertNotNull($pending[0]->next_retry_at);
        $this->assertEquals(0, $pending[1]->retry_count);
    }

    /**
     * TEST CASE F: Existing campaign features (successful dispatch) continue working.
     */
    public function test_case_f_successful_dispatch_works_and_completes_campaign(): void
    {
        Http::fake([
            'https://emails.pabbly.com/api/v2/campaigns' => Http::response([
                'status' => 'success',
                'data' => ['_id' => 'pabbly_camp_success_100'],
            ], 200),
            'https://emails.pabbly.com/api/v2/campaigns/send-to-individual' => Http::response([
                'status' => 'success',
                'data' => ['queued' => 1],
            ], 200),
        ]);

        [$campaign, $recipients] = $this->createApprovedCampaignWithRecipients(1);
        $recipient = $recipients[0];

        $processingService = app(CampaignProcessingService::class);
        $processed = $processingService->processQueuedEmails();

        $this->assertEquals(1, $processed);

        $recipient->refresh();
        $this->assertEquals('sent', $recipient->delivery_status);
        $this->assertEquals('pabbly_camp_success_100', $recipient->provider_message_id);
        $this->assertNotNull($recipient->sent_at);
        $this->assertNull($recipient->next_retry_at);
        $this->assertNull($recipient->error_message);

        $campaign->refresh();
        $this->assertEquals('completed', $campaign->status);
    }

    /**
     * TEST CASE G: Duplicate dispatch prevention via atomic recipient lock.
     */
    public function test_case_g_duplicate_dispatch_is_prevented_by_atomic_lock(): void
    {
        [$campaign, $recipients] = $this->createApprovedCampaignWithRecipients(1);
        $recipient = $recipients[0];

        // Simulate Worker 1 acquiring recipient lock
        $lock = Cache::lock("pabbly:recipient_lock:{$recipient->id}", 60);
        $this->assertTrue($lock->get());

        // Worker 2 attempts to process the queue while lock is held
        $processingService = app(CampaignProcessingService::class);
        $processed = $processingService->processQueuedEmails();

        // Worker 2 must have skipped this locked recipient
        $this->assertEquals(0, $processed, 'Worker 2 should not process a locked recipient');
        $recipient->refresh();
        $this->assertEquals('queued', $recipient->delivery_status, 'Recipient must remain queued');

        $lock->release();
    }

    /**
     * TEST CASE H: Retries stop after configured maximum attempts and mark permanently failed.
     */
    public function test_case_h_retry_exhaustion_marks_permanently_failed(): void
    {
        Http::fake([
            'https://emails.pabbly.com/api/v2/campaigns' => Http::response([
                'status' => 'success',
                'data' => ['_id' => 'pabbly_camp_exhaust_429'],
            ], 200),
            'https://emails.pabbly.com/api/v2/campaigns/send-to-individual' => Http::response([
                'status' => 'error',
                'message' => 'Too many requests from this IP or user, please try again later.',
            ], 429),
        ]);

        [$campaign, $recipients] = $this->createApprovedCampaignWithRecipients(1);
        $recipient = $recipients[0];

        // Simulate recipient that has already reached maximum retries (e.g. 3)
        $recipient->update([
            'retry_count' => 3,
            'next_retry_at' => Carbon::now()->subMinute(), // eligible now
        ]);

        $processingService = app(CampaignProcessingService::class);
        $processed = $processingService->processQueuedEmails();

        $this->assertEquals(0, $processed);

        $recipient->refresh();
        $this->assertEquals('failed', $recipient->delivery_status, 'Recipient must be permanently failed once retries are exhausted');
        $this->assertStringContainsString('Rate limit retry attempts exhausted', $recipient->error_message);

        $member = CampaignMember::find($recipient->campaign_member_id);
        $this->assertEquals('failed', $member->status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\RecipientLog;
use App\Models\RecipientOpen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardDeliveryLocationMapTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->webhookToken = config('pabbly.webhook_token', '465391a5d62179d5fe916746c44bacc8bdaf7e7e1bf8b015532e8985f0246327');
    }

    /**
     * Test Dashboard Stats API returns geo_data with delivered and open counts.
     */
    public function test_dashboard_stats_api_returns_delivery_and_open_locations(): void
    {
        $admin = User::where('username', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create([
                'username' => 'admin',
                'role' => 'admin',
                'email' => 'admin@test.com',
            ]);
        }

        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Map Test Campaign',
            'body' => '<p>Hello</p>',
            'sending_domain' => 'test.com',
            'from_address' => 'from@test.com',
            'reply_to' => 'reply@test.com',
            'user_id' => $admin->id,
            'total_requested' => 3,
            'total_approved' => 3,
            'status' => 'completed',
        ]);

        $logDelivered = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'deliv.map@example.com',
            'salesforce_record_id' => '003SF_MAP_01',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'country' => 'United States',
            'region' => 'California',
            'city' => 'San Francisco',
            'tracking_token' => (string) Str::uuid(),
            'sent_at' => now(),
        ]);

        $logOpened = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'opened.map@example.com',
            'salesforce_record_id' => '003SF_MAP_02',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'opened',
            'country' => 'United States',
            'region' => 'California',
            'city' => 'San Francisco',
            'tracking_token' => (string) Str::uuid(),
            'sent_at' => now(),
        ]);

        RecipientOpen::create([
            'recipient_log_id' => $logOpened->id,
            'opened_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'country' => 'United States',
            'region' => 'California',
            'city' => 'San Francisco',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/stats?campaign_id=' . $campaign->id);
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('geo_data', $data);
        $this->assertNotEmpty($data['geo_data']);

        $firstGeo = $data['geo_data'][0];
        $this->assertEquals('United States', $firstGeo['country']);
        $this->assertEquals('San Francisco', $firstGeo['city']);
        $this->assertEquals(2, $firstGeo['delivered_count']);
        $this->assertEquals(1, $firstGeo['open_count']);
    }

    /**
     * Test Pabbly delivery webhook records location telemetry.
     */
    public function test_pabbly_delivery_webhook_records_location_telemetry(): void
    {
        $admin = User::where('username', 'admin')->first();
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Webhook Delivery Location Test',
            'body' => '<p>Test</p>',
            'sending_domain' => 'test.com',
            'from_address' => 'from@test.com',
            'reply_to' => 'reply@test.com',
            'user_id' => $admin ? $admin->id : 1,
            'status' => 'sending',
        ]);

        $log = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'webhook.deliv.loc@example.com',
            'salesforce_record_id' => '003SF_WH_DELIV',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'provider_message_id' => 'msg-loc-test-123',
            'tracking_token' => (string) Str::uuid(),
            'sent_at' => now(),
        ]);

        $payload = [
            'event_type' => 'email_delivered',
            'data' => [
                'email' => 'webhook.deliv.loc@example.com',
                'message_id' => 'msg-loc-test-123',
                'country' => 'United Kingdom',
                'region' => 'England',
                'city' => 'London',
            ],
        ];

        $res = $this->postJson("/api/webhooks/pabbly?token={$this->webhookToken}", $payload);
        $res->assertStatus(200)->assertJson(['event' => 'delivered']);

        $log->refresh();
        $this->assertEquals('delivered', $log->delivery_status);
        $this->assertEquals('United Kingdom', $log->country);
        $this->assertEquals('England', $log->region);
        $this->assertEquals('London', $log->city);
    }

    /**
     * Test Dashboard view contains OpenStreetMap tile layer and no CartoDB API key requirement.
     */
    public function test_dashboard_view_renders_openstreetmap_tile_layer(): void
    {
        $admin = User::where('username', 'admin')->first();
        $res = $this->actingAs($admin)->get('/dashboard');
        $res->assertStatus(200);

        // Verify OpenStreetMap tile URL is present
        $res->assertSee('tile.openstreetmap.org', false);
        // Verify deprecated CartoDB tile layer is not present
        $res->assertDontSee('basemaps.cartocdn.com', false);
        // Verify email delivery map and telemetry labels
        $res->assertSee('Email Delivery & Telemetry Map', false);
        $res->assertSee('Top Delivery & Engagement Hubs', false);

        // Verify charts removed as requested
        $res->assertDontSee('Dispatch Volumetric Trend');
        $res->assertDontSee('Engagement Open Timeline');
        $res->assertDontSee('id="volumeChart"', false);
        $res->assertDontSee('id="openTimelineChart"', false);
    }

    /**
     * Test delivered count includes 'sent', 'delivered', 'opened', 'unsubscribed' and open_rate is correct.
     */
    public function test_dashboard_stats_accurately_counts_delivered_and_open_rate(): void
    {
        $admin = User::where('username', 'admin')->first();
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Accuracy Test Campaign',
            'body' => '<p>Hello</p>',
            'sending_domain' => 'test.com',
            'from_address' => 'from@test.com',
            'reply_to' => 'reply@test.com',
            'user_id' => $admin->id,
            'total_requested' => 10,
            'total_approved' => 10,
            'status' => 'completed',
        ]);

        // 5 emails sent (inbox delivered, not opened yet)
        for ($i = 0; $i < 5; $i++) {
            RecipientLog::create([
                'campaign_id' => $campaign->id,
                'email' => "sent{$i}@example.com",
                'salesforce_record_id' => "003ACC_S_{$i}",
                'salesforce_object' => 'Contact',
                'record_owner_id' => 'admin',
                'decision' => 'approved',
                'delivery_status' => 'sent',
                'tracking_token' => (string) Str::uuid(),
                'sent_at' => now(),
            ]);
        }

        // 3 emails opened
        for ($i = 0; $i < 3; $i++) {
            $log = RecipientLog::create([
                'campaign_id' => $campaign->id,
                'email' => "opened{$i}@example.com",
                'salesforce_record_id' => "003ACC_O_{$i}",
                'salesforce_object' => 'Contact',
                'record_owner_id' => 'admin',
                'decision' => 'approved',
                'delivery_status' => 'opened',
                'tracking_token' => (string) Str::uuid(),
                'sent_at' => now(),
            ]);
            RecipientOpen::create([
                'recipient_log_id' => $log->id,
                'opened_at' => now(),
            ]);
        }

        // 1 email unsubscribed
        RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'unsub@example.com',
            'salesforce_record_id' => '003ACC_U_1',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'unsubscribed',
            'tracking_token' => (string) Str::uuid(),
            'sent_at' => now(),
        ]);

        // 1 email failed before send
        RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'failed@example.com',
            'salesforce_record_id' => '003ACC_F_1',
            'salesforce_object' => 'Contact',
            'record_owner_id' => 'admin',
            'decision' => 'approved',
            'delivery_status' => 'failed',
            'tracking_token' => (string) Str::uuid(),
            'sent_at' => null,
        ]);

        // 2 emails queued in queue for dispatch
        for ($i = 0; $i < 2; $i++) {
            RecipientLog::create([
                'campaign_id' => $campaign->id,
                'email' => "queued{$i}@example.com",
                'salesforce_record_id' => "003ACC_Q_{$i}",
                'salesforce_object' => 'Contact',
                'record_owner_id' => 'admin',
                'decision' => 'approved',
                'delivery_status' => 'queued',
                'tracking_token' => (string) Str::uuid(),
                'sent_at' => null,
            ]);
        }

        // Total sent = 5 (sent) + 3 (opened) + 1 (unsubscribed) = 9
        // Undelivered = 1
        // Delivered = total_sent - undelivered = 9 - 1 = 8
        // Opened = 3
        // Total triggered = 9 (sent) + 1 (failed) = 10
        // Queued = 2
        // Open rate = 3 / 8 * 100 = 37.5%
        $res = $this->actingAs($admin)->getJson('/api/dashboard/stats?campaign_id=' . $campaign->id);
        $res->assertStatus(200);

        $data = $res->json();
        $this->assertEquals(10, $data['counters']['total_triggered']);
        $this->assertEquals(2, $data['counters']['queued']);
        $this->assertEquals(9, $data['counters']['total_sent']);
        $this->assertEquals(8, $data['counters']['delivered']);
        $this->assertEquals(3, $data['counters']['opened']);
        $this->assertEquals(1, $data['counters']['undelivered']);
        $this->assertEquals(37.5, $data['counters']['open_rate']);

        // Check recipient list drilldown for triggered status
        $resModalTriggered = $this->actingAs($admin)->getJson('/api/dashboard/recipient-list?status=triggered&campaign_id=' . $campaign->id);
        $resModalTriggered->assertStatus(200);
        $this->assertCount(10, $resModalTriggered->json());

        // Check recipient list drilldown for queued status
        $resModalQueued = $this->actingAs($admin)->getJson('/api/dashboard/recipient-list?status=queued&campaign_id=' . $campaign->id);
        $resModalQueued->assertStatus(200);
        $this->assertCount(2, $resModalQueued->json());

        // Check recipient list drilldown for delivered status
        $resModal = $this->actingAs($admin)->getJson('/api/dashboard/recipient-list?status=delivered&campaign_id=' . $campaign->id);
        $resModal->assertStatus(200);
        $this->assertCount(9, $resModal->json());

        // Check dashboard view contains Total Email Triggered
        $resView = $this->actingAs($admin)->get('/dashboard');
        $resView->assertStatus(200);
        $resView->assertSee('Total Email Triggered');
    }
}

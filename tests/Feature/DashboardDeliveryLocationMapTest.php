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
}

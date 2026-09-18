<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the Manager Approval Workflow.
 *
 * Standard users (role='user') must always have their campaigns placed in
 * 'pending_approval'. No emails may be dispatched until the manager approves.
 * Admins and Managers bypass approval entirely.
 */
class CampaignApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $standardUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'username' => 'admin', 'daily_limit' => 1000]);
        $this->manager = User::factory()->create(['role' => 'manager', 'username' => 'manager', 'daily_limit' => 1000]);
        $this->standardUser = User::factory()->create([
            'role'       => 'user',
            'username'   => 'user',
            'manager_id' => $this->manager->id,
            'daily_limit' => 1000,
        ]);
    }

    private function campaignPayload(array $overrides = []): array
    {
        return array_merge([
            'subject'          => 'Test Campaign',
            'body'             => '<p>Hello</p>',
            'sending_domain'   => 'example.com',
            'from_address'     => 'sender@example.com',
            'recipient_emails' => ['test@test.com'],
        ], $overrides);
    }

    private function actingAsUser(User $user): static
    {
        \Illuminate\Support\Facades\Auth::logout();
        return $this->actingAs($user)->withSession([
            'user_id'  => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'email'    => $user->email,
        ]);
    }

    /** @test */
    public function standard_user_campaign_is_placed_in_pending_approval(): void
    {
        $response = $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'pending_approval']);

        $this->assertDatabaseHas('campaigns', [
            'user_id' => $this->standardUser->id,
            'status'  => 'pending_approval',
        ]);
    }

    /** @test */
    public function standard_user_recipient_logs_have_pending_approval_delivery_status(): void
    {
        // Seed a mock CRM record owned by the standard user so the recipient passes eligibility.
        $mockId = 'SF001TEST001';
        DB::table('salesforce_mock_records')->insert([
            'id'             => $mockId,
            'object_type'    => 'Lead',
            'first_name'     => 'Test',
            'last_name'      => 'Lead',
            'email'          => 'test-lead@example.com',
            'owner_id'       => $this->standardUser->username,
            'opted_out'      => false,
            'status'         => 'New',
            'consent_status' => 'valid',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', array_merge($this->campaignPayload(), [
                'recipient_ids'    => [$mockId],
                'recipient_emails' => [],
            ]))
            ->assertJson(['success' => true]);

        $campaign = Campaign::where('user_id', $this->standardUser->id)->firstOrFail();

        // Approved recipients must have delivery_status = pending_approval (not queued/sent)
        $this->assertDatabaseHas('recipient_logs', [
            'campaign_id'     => $campaign->id,
            'decision'        => 'approved',
            'delivery_status' => 'pending_approval',
        ]);
    }

    /** @test */
    public function no_email_processing_is_triggered_for_pending_approval_campaign(): void
    {
        $this->mock(CampaignProcessingService::class, function ($mock) {
            $mock->shouldNotReceive('processQueuedEmails');
        });

        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', $this->campaignPayload())
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_campaign_goes_directly_to_queued_without_approval(): void
    {
        $response = $this->actingAsUser($this->admin)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'queued']);

        $this->assertDatabaseHas('campaigns', [
            'user_id' => $this->admin->id,
            'status'  => 'queued',
        ]);
    }

    /** @test */
    public function manager_campaign_goes_directly_to_queued_without_approval(): void
    {
        $response = $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'queued']);

        $this->assertDatabaseHas('campaigns', [
            'user_id' => $this->manager->id,
            'status'  => 'queued',
        ]);
    }

    /** @test */
    public function standard_user_scheduled_campaign_is_pending_approval_not_scheduled(): void
    {
        $scheduledAt = now()->addHour()->format('Y-m-d H:i:s');

        $response = $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', $this->campaignPayload(['scheduled_at' => $scheduledAt]));

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'pending_approval']);

        $this->assertDatabaseHas('campaigns', [
            'user_id' => $this->standardUser->id,
            'status'  => 'pending_approval',
        ]);
    }

    /** @test */
    public function manager_can_approve_pending_campaign_and_it_transitions_to_queued(): void
    {
        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->standardUser->id)->firstOrFail();
        $this->assertEquals('pending_approval', $campaign->status);

        $response = $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'Looks good',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $campaign->refresh();
        $this->assertEquals('queued', $campaign->status);
        $this->assertEquals($this->manager->id, $campaign->approved_by);
        $this->assertEquals('Looks good', $campaign->approval_remark);
    }

    /** @test */
    public function manager_can_reject_pending_campaign(): void
    {
        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->standardUser->id)->firstOrFail();

        $response = $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'reject',
                'remark'      => 'Not compliant',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $campaign->refresh();
        $this->assertEquals('rejected', $campaign->status);
        $this->assertEquals($this->manager->id, $campaign->approved_by);
        $this->assertEquals('Not compliant', $campaign->approval_remark);

        $this->assertDatabaseHas('recipient_logs', [
            'campaign_id'     => $campaign->id,
            'delivery_status' => 'blocked',
            'decision'        => 'blocked',
        ]);
    }

    /** @test */
    public function double_approve_is_rejected_with_409(): void
    {
        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->standardUser->id)->firstOrFail();

        // First approval
        $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'OK',
            ])->assertStatus(200);

        // Second approval attempt must fail
        $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'Again',
            ])->assertStatus(409);
    }

    /** @test */
    public function standard_user_without_manager_gets_warning_in_response(): void
    {
        $orphanUser = User::factory()->create([
            'role'        => 'user',
            'username'    => 'orphan',
            'manager_id'  => null,
            'daily_limit' => 1000,
        ]);

        $response = $this->actingAsUser($orphanUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'pending_approval'])
            ->assertJsonPath('warning', fn($w) => $w !== null && str_contains($w, 'No manager'));
    }

    /** @test */
    public function standard_user_cannot_approve_campaigns(): void
    {
        $otherUser = User::factory()->create(['role' => 'user', 'username' => 'other', 'daily_limit' => 1000]);

        $this->actingAsUser($otherUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $otherUser->id)->firstOrFail();

        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'sneaky',
            ])->assertStatus(403);
    }

    /** @test */
    public function campaign_detail_view_renders_campaigns_sidebar_with_pending_and_approved_campaigns(): void
    {
        // 1. Create a pending campaign by standard user
        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', array_merge($this->campaignPayload(), [
                'subject' => 'Urgent Pending Outreach',
            ]))
            ->assertStatus(200);

        $pendingCampaign = Campaign::where('subject', 'Urgent Pending Outreach')->firstOrFail();

        // 2. Create an approved/queued campaign by manager
        $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/send', array_merge($this->campaignPayload(), [
                'subject' => 'Approved Manager Blast',
            ]))
            ->assertStatus(200);

        $approvedCampaign = Campaign::where('subject', 'Approved Manager Blast')->firstOrFail();

        // 3. Visit the detail view for the pending campaign as manager
        $response = $this->actingAsUser($this->manager)
            ->get("/campaign/{$pendingCampaign->id}");

        $response->assertStatus(200);
        $response->assertViewHas('sidebarCampaigns');
        $response->assertSee('campaigns-sidebar-col');
        $response->assertSee('tab-btn-pending');
        $response->assertSee('tab-btn-approved');
        $response->assertSee('Urgent Pending Outreach');
        $response->assertSee('Approved Manager Blast');
    }

    /** @test */
    public function campaign_list_view_shows_all_sent_and_pending_campaigns_of_user_account(): void
    {
        // 1. Pending campaign for standardUser
        $this->actingAsUser($this->standardUser)
            ->postJson('/api/campaign/send', array_merge($this->campaignPayload(), [
                'subject' => 'My Account Pending Campaign',
            ]))
            ->assertStatus(200);

        // 2. Sent / Queued campaign for standardUser (create as admin, update user_id to standardUser to simulate approved)
        $approvedCampaign = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'My Account Sent Campaign',
            'body' => 'Body text',
            'sending_domain' => 'example.com',
            'from_address' => 'sender@example.com',
            'reply_to' => 'sender@example.com',
            'user_id' => $this->standardUser->id,
            'status' => 'completed',
            'total_requested' => 10,
            'total_approved' => 10,
            'total_blocked' => 0,
        ]);

        // 3. Campaign belonging to another user
        $otherUser = User::factory()->create(['role' => 'user', 'username' => 'other_user', 'daily_limit' => 1000]);
        $otherCampaign = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Secret Other User Campaign',
            'body' => 'Body text',
            'sending_domain' => 'example.com',
            'from_address' => 'sender@example.com',
            'reply_to' => 'sender@example.com',
            'user_id' => $otherUser->id,
            'status' => 'completed',
            'total_requested' => 5,
            'total_approved' => 5,
            'total_blocked' => 0,
        ]);

        // 4. Standard user accesses /campaign/list
        $response = $this->actingAsUser($this->standardUser)
            ->get('/campaign/list');

        $response->assertStatus(200);
        $response->assertViewHas('campaigns');
        $response->assertSee('My Account Pending Campaign');
        $response->assertSee('My Account Sent Campaign');
        $response->assertDontSee('Secret Other User Campaign');

        // Unauthenticated access must redirect to login
        \Illuminate\Support\Facades\Auth::logout();
        session()->flush();
        $this->flushSession();
        $this->get('/campaign/list')
            ->assertRedirect(route('login'));
    }
}
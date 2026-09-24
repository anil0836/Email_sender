<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignApproval;
use App\Models\RecipientLog;
use App\Models\User;
use App\Services\CampaignProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the 1-Level Campaign Approval Workflow Hierarchy:
 * - User -> Line Manager (or Manager if directly assigned) -> 1 level approval
 * - Line Manager -> Manager -> 1 level approval
 * - Manager / Admin -> Direct sending (no approval required)
 * - Strict send-time protection & incomplete hierarchy prevention.
 */
class CampaignApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $lineManager;
    private User $userUnderLineManager;
    private User $userUnderManager;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Admin
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin_test',
            'daily_limit' => 5000,
        ]);

        // 2. Manager (Mark)
        $this->manager = User::factory()->create([
            'role' => 'manager',
            'username' => 'manager_mark',
            'daily_limit' => 3000,
        ]);

        // 3. Line Manager (Marcus) under Manager (Mark)
        $this->lineManager = User::factory()->create([
            'role' => 'line_manager',
            'username' => 'line_marcus',
            'manager_id' => $this->manager->id,
            'daily_limit' => 2000,
        ]);

        // 4. User (Abraham) under Line Manager (Marcus)
        $this->userUnderLineManager = User::factory()->create([
            'role' => 'user',
            'username' => 'user_abraham',
            'manager_id' => $this->lineManager->id,
            'daily_limit' => 1000,
        ]);

        // 5. User (Ben) under Manager (Mark)
        $this->userUnderManager = User::factory()->create([
            'role' => 'user',
            'username' => 'user_ben',
            'manager_id' => $this->manager->id,
            'daily_limit' => 1000,
        ]);
    }

    private function campaignPayload(array $overrides = []): array
    {
        return array_merge([
            'subject'          => 'Test Campaign Approval Flow',
            'body'             => '<p>Approval test body</p>',
            'sending_domain'   => 'example.com',
            'from_address'     => 'sender@example.com',
            'recipient_emails' => ['lead@example.com'],
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

    public function test_user_under_line_manager_campaign_goes_to_pending_line_manager(): void
    {
        $response = $this->actingAsUser($this->userUnderLineManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)
            ->assertJson([
                'success'             => true,
                'status'              => 'pending_approval',
                'approval_stage'      => 'pending_line_manager',
                'current_approver_id' => $this->lineManager->id,
            ]);

        $this->assertDatabaseHas('campaigns', [
            'user_id'             => $this->userUnderLineManager->id,
            'status'              => 'pending_approval',
            'line_manager_id'     => $this->lineManager->id,
            'current_approver_id' => $this->lineManager->id,
        ]);

        $campaign = Campaign::where('user_id', $this->userUnderLineManager->id)->firstOrFail();
        $this->assertDatabaseHas('campaign_approvals', [
            'campaign_id' => $campaign->id,
            'approver_id' => $this->userUnderLineManager->id,
            'action'      => 'submitted',
            'new_status'  => 'pending_approval',
        ]);
    }

    public function test_line_manager_can_approve_subordinate_campaign_to_queued(): void
    {
        $this->actingAsUser($this->userUnderLineManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->userUnderLineManager->id)->firstOrFail();
        $this->assertEquals('pending_approval', $campaign->status);

        // Line Manager approves
        $response = $this->actingAsUser($this->lineManager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'Approved by Line Manager',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'new_status' => 'queued',
            ]);

        $campaign->refresh();
        $this->assertEquals('queued', $campaign->status);
        $this->assertNull($campaign->current_approver_id);
        $this->assertEquals($this->lineManager->id, $campaign->approved_by);
        $this->assertEquals('Approved by Line Manager', $campaign->approval_remark);
        $this->assertTrue($campaign->isFullyApproved());

        $this->assertDatabaseHas('campaign_approvals', [
            'campaign_id'     => $campaign->id,
            'approver_id'     => $this->lineManager->id,
            'approver_role'   => 'line_manager',
            'action'          => 'approved',
            'previous_status' => 'pending_approval',
            'new_status'      => 'queued',
        ]);
    }

    public function test_user_under_manager_campaign_goes_to_pending_manager(): void
    {
        $response = $this->actingAsUser($this->userUnderManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)
            ->assertJson([
                'success'             => true,
                'status'              => 'pending_approval',
                'approval_stage'      => 'pending_manager',
                'current_approver_id' => $this->manager->id,
            ]);

        $this->assertDatabaseHas('campaigns', [
            'user_id'             => $this->userUnderManager->id,
            'status'              => 'pending_approval',
            'manager_user_id'     => $this->manager->id,
            'current_approver_id' => $this->manager->id,
        ]);
    }

    public function test_manager_can_approve_user_campaign_to_queued(): void
    {
        $this->actingAsUser($this->userUnderManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->userUnderManager->id)->firstOrFail();
        $this->assertEquals('pending_approval', $campaign->status);

        $response = $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'Manager approved directly',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $campaign->refresh();
        $this->assertEquals('queued', $campaign->status);
        $this->assertNull($campaign->current_approver_id);
        $this->assertEquals($this->manager->id, $campaign->approved_by);
        $this->assertTrue($campaign->isFullyApproved());
    }

    public function test_line_manager_campaign_goes_to_pending_manager(): void
    {
        $response = $this->actingAsUser($this->lineManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)
            ->assertJson([
                'success'             => true,
                'status'              => 'pending_approval',
                'approval_stage'      => 'pending_manager',
                'current_approver_id' => $this->manager->id,
            ]);

        $this->assertDatabaseHas('campaigns', [
            'user_id'             => $this->lineManager->id,
            'status'              => 'pending_approval',
            'manager_user_id'     => $this->manager->id,
            'current_approver_id' => $this->manager->id,
        ]);
    }

    public function test_manager_campaign_goes_directly_to_queued_without_approval(): void
    {
        $response = $this->actingAsUser($this->manager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'queued']);

        $campaign = Campaign::where('user_id', $this->manager->id)->firstOrFail();
        $this->assertEquals('queued', $campaign->status);
        $this->assertNull($campaign->current_approver_id);
        $this->assertTrue($campaign->isFullyApproved());
    }

    public function test_admin_campaign_goes_directly_to_queued_without_approval(): void
    {
        $response = $this->actingAsUser($this->admin)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'queued']);

        $campaign = Campaign::where('user_id', $this->admin->id)->firstOrFail();
        $this->assertEquals('queued', $campaign->status);
        $this->assertNull($campaign->current_approver_id);
        $this->assertTrue($campaign->isFullyApproved());
    }

    public function test_incomplete_hierarchy_blocks_submission_with_422(): void
    {
        $orphanUser = User::factory()->create([
            'role'        => 'user',
            'username'    => 'orphan_user',
            'manager_id'  => null,
            'daily_limit' => 1000,
        ]);

        $response = $this->actingAsUser($orphanUser)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(422)
            ->assertJsonPath('error', fn($e) => str_contains($e, 'hierarchy is incomplete'));

        // No campaign should be created in DB
        $this->assertDatabaseMissing('campaigns', [
            'user_id' => $orphanUser->id,
        ]);
    }

    public function test_line_manager_with_missing_manager_blocks_submission_with_422(): void
    {
        $orphanLineManager = User::factory()->create([
            'role'        => 'line_manager',
            'username'    => 'orphan_lm',
            'manager_id'  => null,
            'daily_limit' => 2000,
        ]);

        $response = $this->actingAsUser($orphanLineManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $response->assertStatus(422)
            ->assertJsonPath('error', fn($e) => str_contains($e, 'No Manager is assigned'));

        $this->assertDatabaseMissing('campaigns', [
            'user_id' => $orphanLineManager->id,
        ]);
    }

    public function test_rejection_marks_campaign_and_recipients_rejected_with_audit(): void
    {
        $this->actingAsUser($this->userUnderLineManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->userUnderLineManager->id)->firstOrFail();

        $response = $this->actingAsUser($this->lineManager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'reject',
                'remark'      => 'Inappropriate copy',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'new_status' => 'rejected',
            ]);

        $campaign->refresh();
        $this->assertEquals('rejected', $campaign->status);
        $this->assertEquals($this->lineManager->id, $campaign->rejected_by);
        $this->assertEquals('Inappropriate copy', $campaign->rejection_reason);
        $this->assertFalse($campaign->isFullyApproved());

        $this->assertDatabaseHas('campaign_approvals', [
            'campaign_id'   => $campaign->id,
            'approver_id'   => $this->lineManager->id,
            'action'        => 'rejected',
            'comment'       => 'Inappropriate copy',
        ]);

        $this->assertDatabaseHas('recipient_logs', [
            'campaign_id'     => $campaign->id,
            'delivery_status' => 'blocked',
            'decision'        => 'blocked',
        ]);
    }

    public function test_creator_cannot_approve_own_campaign(): void
    {
        $this->actingAsUser($this->userUnderLineManager)
            ->postJson('/api/campaign/send', $this->campaignPayload());

        $campaign = Campaign::where('user_id', $this->userUnderLineManager->id)->firstOrFail();

        $response = $this->actingAsUser($this->userUnderLineManager)
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaign->id,
                'decision'    => 'approve',
                'remark'      => 'Self approval attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_campaign_processing_service_never_sends_unapproved_campaigns(): void
    {
        // Create an unapproved campaign directly
        $unapprovedCampaign = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Unapproved Campaign',
            'body' => 'Secret text',
            'sending_domain' => 'example.com',
            'from_address' => 'sender@example.com',
            'reply_to' => 'sender@example.com',
            'user_id' => $this->userUnderLineManager->id,
            'status' => 'pending_approval',
            'line_manager_id' => $this->lineManager->id,
            'current_approver_id' => $this->lineManager->id,
            'total_requested' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
        ]);

        RecipientLog::create([
            'campaign_id' => $unapprovedCampaign->id,
            'email' => 'victim@example.com',
            'salesforce_record_id' => '003SF0000000_123',
            'salesforce_object' => 'Contact',
            'record_owner_id' => $this->userUnderLineManager->username,
            'decision' => 'approved',
            'delivery_status' => 'pending_approval',
            'tracking_token' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $service = app(CampaignProcessingService::class);
        $count = $service->processQueuedEmails();

        // 0 emails should be processed
        $this->assertEquals(0, $count);
        $unapprovedCampaign->refresh();
        $this->assertEquals('pending_approval', $unapprovedCampaign->status);
    }
}
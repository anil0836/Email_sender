<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Models\User;
use App\Services\TeamService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamManagerCampaignAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $managerAdriana;
    protected User $memberAlice;
    protected User $managerBrian;
    protected User $memberBob;
    protected User $independentUser;
    protected TeamService $teamService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->teamService = app(TeamService::class);

        $this->admin = User::where('username', 'admin')->first();

        // 1. Setup Team Adriana:
        // Salesforce Manager User
        $sfUserAdriana = SalesforceUser::create([
            'salesforce_id' => '005ADRIANA000001',
            'name' => 'Adriana Cowart',
            'first_name' => 'Adriana',
            'last_name' => 'Cowart',
            'username' => 'adriana@b2bexportsllc.com',
            'email' => 'adriana@b2bexportsllc.com',
            'is_active' => true,
        ]);

        // Local Manager User
        $this->managerAdriana = User::create([
            'username' => 'adriana',
            'name' => 'Adriana Cowart',
            'email' => 'adriana@b2bexportsllc.com',
            'password' => Hash::make('secret123'),
            'role' => 'user', // Local role is user, but is SF Team Manager!
            'emp_id' => 'EMP_ADRIANA',
        ]);

        // Team Member Alice
        $this->memberAlice = User::create([
            'username' => 'alice',
            'name' => 'Alice Walker',
            'email' => 'alice@b2bexportsllc.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'emp_id' => 'EMP_ALICE',
        ]);

        SalesforceSfUser::create([
            'salesforce_id' => 'a00SF0000000001',
            'name' => 'Alice Walker',
            'emp_email' => 'alice@b2bexportsllc.com',
            'emp_code' => 'EMP_ALICE',
            'team' => 'Adriana',
            'is_active' => true,
        ]);

        // 2. Setup Team Brian:
        // Salesforce Manager User
        $sfUserBrian = SalesforceUser::create([
            'salesforce_id' => '005BRIAN0000001',
            'name' => 'Brian Clarke',
            'first_name' => 'Brian',
            'last_name' => 'Clarke',
            'username' => 'brian@b2bexportsllc.com',
            'email' => 'brian@b2bexportsllc.com',
            'is_active' => true,
        ]);

        // Local Manager User
        $this->managerBrian = User::create([
            'username' => 'brian',
            'name' => 'Brian Clarke',
            'email' => 'brian@b2bexportsllc.com',
            'password' => Hash::make('secret123'),
            'role' => 'manager',
            'emp_id' => 'EMP_BRIAN',
        ]);

        // Team Member Bob
        $this->memberBob = User::create([
            'username' => 'bob',
            'name' => 'Bob Stone',
            'email' => 'bob@b2bexportsllc.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'emp_id' => 'EMP_BOB',
        ]);

        SalesforceSfUser::create([
            'salesforce_id' => 'a00SF0000000002',
            'name' => 'Bob Stone',
            'emp_email' => 'bob@b2bexportsllc.com',
            'emp_code' => 'EMP_BOB',
            'team' => 'Brian',
            'is_active' => true,
        ]);

        // 3. Setup Independent User
        $this->independentUser = User::create([
            'username' => 'charlie',
            'name' => 'Charlie Day',
            'email' => 'charlie@b2bexportsllc.com',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'emp_id' => 'EMP_CHARLIE',
        ]);
    }

    public function test_team_and_manager_resolution_helper_methods(): void
    {
        // 1. Team resolution
        $this->assertEquals('Adriana', $this->teamService->resolveUserTeam($this->memberAlice));
        $this->assertEquals('Brian', $this->teamService->resolveUserTeam($this->memberBob));
        $this->assertNull($this->teamService->resolveUserTeam($this->independentUser));

        // 2. Manager resolution
        $mgrAdrianaInfo = $this->teamService->resolveTeamManager('Adriana');
        $this->assertNotNull($mgrAdrianaInfo);
        $this->assertEquals('005ADRIANA000001', $mgrAdrianaInfo['salesforce_id']);
        $this->assertEquals($this->managerAdriana->id, $mgrAdrianaInfo['local_user_id']);

        // 3. Manager check
        $this->assertTrue($this->managerAdriana->isTeamManager());
        $this->assertTrue($this->managerBrian->isTeamManager());
        $this->assertFalse($this->memberAlice->isTeamManager());
        $this->assertFalse($this->independentUser->isTeamManager());

        // 4. Managed teams
        $this->assertContains('Adriana', $this->managerAdriana->managedTeams());
        $this->assertContains('Brian', $this->managerBrian->managedTeams());
    }

    public function test_creator_team_and_manager_are_resolved_when_dispatching_campaign(): void
    {
        $campaignRes = $this->actingAs($this->memberAlice)
            ->withSession([
                'user_id' => $this->memberAlice->id,
                'username' => $this->memberAlice->username,
                'role' => $this->memberAlice->role,
                'email' => $this->memberAlice->email,
            ])
            ->postJson('/api/campaign/send', [
                'subject' => 'Alice Team Delivery Campaign',
                'sending_domain' => 'proitbuyer.com',
                'from_address' => 'alice@proitbuyer.com',
                'reply_to' => 'alice@b2bexportsllc.com',
                'body' => '<p>Hello from Alice</p>',
                'recipient_emails' => ['lead1@testcorp.com'],
            ]);

        $campaignRes->assertStatus(200);
        $campaignId = $campaignRes->json('campaign_id');
        $this->assertNotEmpty($campaignId);

        $campaign = Campaign::find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertEquals('Adriana', $campaign->team);
        $this->assertEquals('005ADRIANA000001', $campaign->manager_salesforce_id);
        $this->assertEquals($this->managerAdriana->id, $campaign->manager_user_id);
    }

    public function test_team_manager_can_access_all_campaigns_in_managed_team(): void
    {
        // Create campaign under Team Adriana
        $campaignAlice = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Alice Campaign in Team Adriana',
            'body' => '<p>Test</p>',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'alice@proitbuyer.com',
            'reply_to' => 'alice@b2bexportsllc.com',
            'user_id' => $this->memberAlice->id,
            'team' => 'Adriana',
            'manager_salesforce_id' => '005ADRIANA000001',
            'manager_user_id' => $this->managerAdriana->id,
            'total_requested' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
            'status' => 'pending_approval',
        ]);

        // Create campaign under Team Brian
        $campaignBob = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Bob Campaign in Team Brian',
            'body' => '<p>Test</p>',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'bob@proitbuyer.com',
            'reply_to' => 'bob@b2bexportsllc.com',
            'user_id' => $this->memberBob->id,
            'team' => 'Brian',
            'manager_salesforce_id' => '005BRIAN0000001',
            'manager_user_id' => $this->managerBrian->id,
            'total_requested' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
            'status' => 'pending_approval',
        ]);

        // 1. Manager Adriana querying accessible campaigns
        $adrianaAccessible = Campaign::accessibleBy($this->managerAdriana)->pluck('id')->toArray();
        $this->assertContains($campaignAlice->id, $adrianaAccessible);
        $this->assertNotContains($campaignBob->id, $adrianaAccessible);

        // 2. Manager Brian querying accessible campaigns
        $brianAccessible = Campaign::accessibleBy($this->managerBrian)->pluck('id')->toArray();
        $this->assertContains($campaignBob->id, $brianAccessible);
        $this->assertNotContains($campaignAlice->id, $brianAccessible);

        // 3. Admin sees all
        $adminAccessible = Campaign::accessibleBy($this->admin)->pluck('id')->toArray();
        $this->assertContains($campaignAlice->id, $adminAccessible);
        $this->assertContains($campaignBob->id, $adminAccessible);

        // 4. Regular user Alice sees only Alice
        $aliceAccessible = Campaign::accessibleBy($this->memberAlice)->pluck('id')->toArray();
        $this->assertContains($campaignAlice->id, $aliceAccessible);
        $this->assertNotContains($campaignBob->id, $aliceAccessible);
    }

    public function test_campaign_detail_api_enforces_scoping_and_returns_manager_metadata(): void
    {
        $campaignAlice = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Alice Secure Campaign',
            'body' => '<p>Test Content</p>',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'alice@proitbuyer.com',
            'reply_to' => 'alice@b2bexportsllc.com',
            'user_id' => $this->memberAlice->id,
            'team' => 'Adriana',
            'manager_salesforce_id' => '005ADRIANA000001',
            'manager_user_id' => $this->managerAdriana->id,
            'total_requested' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
            'status' => 'pending_approval',
        ]);

        // 1. Member Alice can access detail
        $resAlice = $this->actingAs($this->memberAlice)
            ->withSession(['user_id' => $this->memberAlice->id, 'username' => $this->memberAlice->username, 'role' => $this->memberAlice->role])
            ->getJson("/api/campaign/{$campaignAlice->id}");
        $resAlice->assertStatus(200);
        $resAlice->assertJsonPath('campaign.team', 'Adriana');
        $resAlice->assertJsonPath('campaign.manager_sf_name', 'Adriana Cowart');

        // 2. Team Manager Adriana can access detail
        $resAdriana = $this->actingAs($this->managerAdriana)
            ->withSession(['user_id' => $this->managerAdriana->id, 'username' => $this->managerAdriana->username, 'role' => $this->managerAdriana->role])
            ->getJson("/api/campaign/{$campaignAlice->id}");
        $resAdriana->assertStatus(200);

        // 3. Other Manager Brian is forbidden (403)
        $resBrian = $this->actingAs($this->managerBrian)
            ->withSession(['user_id' => $this->managerBrian->id, 'username' => $this->managerBrian->username, 'role' => $this->managerBrian->role])
            ->getJson("/api/campaign/{$campaignAlice->id}");
        $resBrian->assertStatus(403);

        // 4. Other Member Bob is forbidden (403)
        $resBob = $this->actingAs($this->memberBob)
            ->withSession(['user_id' => $this->memberBob->id, 'username' => $this->memberBob->username, 'role' => $this->memberBob->role])
            ->getJson("/api/campaign/{$campaignAlice->id}");
        $resBob->assertStatus(403);
    }

    public function test_manager_campaign_approval_authorization(): void
    {
        $campaignAlice = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Alice Approval Pending',
            'body' => '<p>Approval needed</p>',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'alice@proitbuyer.com',
            'reply_to' => 'alice@b2bexportsllc.com',
            'user_id' => $this->memberAlice->id,
            'team' => 'Adriana',
            'manager_salesforce_id' => '005ADRIANA000001',
            'manager_user_id' => $this->managerAdriana->id,
            'total_requested' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
            'status' => 'pending_approval',
        ]);

        // 1. Manager Brian attempts to approve Alice's campaign -> 403 Forbidden
        $unauthRes = $this->actingAs($this->managerBrian)
            ->withSession(['user_id' => $this->managerBrian->id, 'username' => $this->managerBrian->username, 'role' => $this->managerBrian->role])
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaignAlice->id,
                'decision' => 'approve',
                'remark' => 'Unauthorized attempt',
            ]);
        $unauthRes->assertStatus(403);

        // 2. Team Manager Adriana approves Alice's campaign -> 200 OK
        $authRes = $this->actingAs($this->managerAdriana)
            ->withSession(['user_id' => $this->managerAdriana->id, 'username' => $this->managerAdriana->username, 'role' => $this->managerAdriana->role])
            ->postJson('/api/campaign/approve', [
                'campaign_id' => $campaignAlice->id,
                'decision' => 'approve',
                'remark' => 'Approved by Team Manager Adriana',
            ]);
        $authRes->assertStatus(200);

        $campaignAlice->refresh();
        $this->assertEquals('queued', $campaignAlice->status);
        $this->assertEquals($this->managerAdriana->id, $campaignAlice->approved_by);
        $this->assertEquals('Approved by Team Manager Adriana', $campaignAlice->approval_remark);
    }

    public function test_manager_api_campaigns_and_team_members_endpoints(): void
    {
        $campaignAlice = Campaign::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'Alice Team Submission',
            'body' => '<p>Submission</p>',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'alice@proitbuyer.com',
            'reply_to' => 'alice@b2bexportsllc.com',
            'user_id' => $this->memberAlice->id,
            'team' => 'Adriana',
            'manager_salesforce_id' => '005ADRIANA000001',
            'manager_user_id' => $this->managerAdriana->id,
            'total_requested' => 1,
            'total_approved' => 1,
            'total_blocked' => 0,
            'status' => 'pending_approval',
        ]);

        // 1. Manager Adriana views team campaigns via API
        $campRes = $this->actingAs($this->managerAdriana)
            ->withSession(['user_id' => $this->managerAdriana->id, 'username' => $this->managerAdriana->username, 'role' => $this->managerAdriana->role])
            ->getJson('/api/manager/campaigns');

        $campRes->assertStatus(200);
        $campList = $campRes->json();
        $this->assertCount(1, $campList);
        $this->assertEquals($campaignAlice->id, $campList[0]['id']);
        $this->assertEquals('Adriana', $campList[0]['team']);
        $this->assertEquals('Adriana Cowart', $campList[0]['manager_name']);

        // 2. Manager Adriana fetches team members via API
        $memberRes = $this->actingAs($this->managerAdriana)
            ->withSession(['user_id' => $this->managerAdriana->id, 'username' => $this->managerAdriana->username, 'role' => $this->managerAdriana->role])
            ->getJson('/api/manager/team-members');

        $memberRes->assertStatus(200);
        $memberList = $memberRes->json();
        $memberUsernames = array_column($memberList, 'username');
        $this->assertContains('alice', $memberUsernames);
        $this->assertNotContains('bob', $memberUsernames);
    }
}

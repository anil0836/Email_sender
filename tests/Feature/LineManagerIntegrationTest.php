<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignApproval;
use App\Models\SendingDomain;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LineManagerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private Server $server;
    private SendingDomain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Ensure Line Manager Spatie role exists with proper web guard
        Role::firstOrCreate(['name' => 'Line Manager', 'guard_name' => 'web']);

        $this->admin = User::create([
            'emp_id' => 'EMP-ADM',
            'username' => 'admin_test',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'daily_limit' => 5000,
        ]);

        $this->manager = User::create([
            'emp_id' => 'EMP-MGR',
            'username' => 'manager_mark',
            'name' => 'Mark Manager',
            'email' => 'mark@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'manager',
            'daily_limit' => 3000,
        ]);

        $this->server = Server::create([
            'name' => 'Test SMTP',
            'host' => 'smtp.test.com',
            'port' => 587,
            'username' => 'test',
            'password' => 'test',
            'sending_ip' => '127.0.0.1',
            'is_active' => true,
        ]);

        $this->domain = SendingDomain::create([
            'domain_name' => 'example.com',
            'status' => 'enabled',
            'spf_status' => 'verified',
            'dkim_status' => 'verified',
            'dmarc_status' => 'verified',
            'server_id' => $this->server->id,
            'is_default' => true,
            'rate_limit_per_hour' => 1000,
        ]);
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'username' => $this->admin->username,
            'role' => 'admin',
        ]);
    }

    private function actingAsUser(User $user): static
    {
        \Illuminate\Support\Facades\Auth::logout();
        return $this->actingAs($user)->withSession([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ]);
    }

    /**
     * Test 1: Admin creates Line Manager account.
     */
    public function test_admin_creates_line_manager(): void
    {
        $payload = [
            'emp_id' => 'EMP-LM1',
            'username' => 'marcus_lm',
            'email' => 'marcus@example.com',
            'password' => 'password123',
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
            'assigned_server_id' => $this->server->id,
            'assigned_domain_id' => $this->domain->id,
            'daily_limit' => 2000,
        ];

        $response = $this->actingAsAdmin()->postJson('/api/admin/users', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'username' => 'marcus_lm',
            'email' => 'marcus@example.com',
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
            'daily_limit' => 2000,
        ]);

        $created = User::where('username', 'marcus_lm')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('Line Manager') || $created->hasRole('line_manager'));
    }

    /**
     * Test 2: Admin edits user into Line Manager.
     */
    public function test_admin_edits_user_into_line_manager(): void
    {
        $employee = User::create([
            'emp_id' => 'EMP-EMP1',
            'username' => 'john_doe',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'manager_id' => $this->manager->id,
            'daily_limit' => 1000,
        ]);

        $payload = [
            'id' => $employee->id,
            'emp_id' => 'EMP-EMP1',
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
            'daily_limit' => 2500,
            'is_blocked' => 0,
        ];

        $response = $this->actingAsAdmin()->putJson('/api/admin/users', $payload);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $employee->refresh();
        $this->assertEquals('line_manager', $employee->role);
        $this->assertEquals($this->manager->id, $employee->manager_id);
        $this->assertTrue($employee->hasRole('Line Manager') || $employee->hasRole('line_manager'));
    }

    /**
     * Test 3: Admin assigns employee to Line Manager.
     */
    public function test_admin_assigns_employee_to_line_manager(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM2',
            'username' => 'marcus_lm2',
            'name' => 'Marcus LM',
            'email' => 'marcus2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
            'daily_limit' => 2000,
        ]);

        $payload = [
            'emp_id' => 'EMP-ABR',
            'username' => 'abraham',
            'email' => 'abraham@example.com',
            'password' => 'password123',
            'role' => 'user',
            'manager_id' => $lineManager->id,
            'daily_limit' => 1000,
        ];

        $response = $this->actingAsAdmin()->postJson('/api/admin/users', $payload);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'username' => 'abraham',
            'role' => 'user',
            'manager_id' => $lineManager->id,
        ]);
    }

    /**
     * Test 4: Multiple employees assigned to one Line Manager.
     */
    public function test_multiple_employees_assigned_to_line_manager(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM3',
            'username' => 'marcus_lm3',
            'name' => 'Marcus LM',
            'email' => 'marcus3@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
        ]);

        $emp1 = User::create([
            'emp_id' => 'EMP-01',
            'username' => 'emp1',
            'email' => 'emp1@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'manager_id' => $lineManager->id,
        ]);

        $emp2 = User::create([
            'emp_id' => 'EMP-02',
            'username' => 'emp2',
            'email' => 'emp2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'manager_id' => $lineManager->id,
        ]);

        $lineManagerReports = $lineManager->directReports;
        $this->assertCount(2, $lineManagerReports);
        $this->assertTrue($lineManagerReports->contains('id', $emp1->id));
        $this->assertTrue($lineManagerReports->contains('id', $emp2->id));
    }

    /**
     * Test 5: api/admin/managers includes Line Managers as eligible supervisors.
     */
    public function test_api_managers_includes_line_managers(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM4',
            'username' => 'marcus_lm4',
            'name' => 'Marcus LM',
            'email' => 'marcus4@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
        ]);

        $response = $this->actingAsAdmin()->getJson('/api/admin/managers');
        $response->assertStatus(200);

        $data = $response->json();
        $lmInList = collect($data)->firstWhere('username', 'marcus_lm4');
        $this->assertNotNull($lmInList);
        $this->assertEquals('line_manager', $lmInList['role']);
    }

    /**
     * Test 6: Self-assignment as manager is rejected.
     */
    public function test_user_cannot_be_assigned_as_their_own_manager(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM5',
            'username' => 'marcus_lm5',
            'email' => 'marcus5@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
        ]);

        $payload = [
            'id' => $lineManager->id,
            'emp_id' => 'EMP-LM5',
            'username' => 'marcus_lm5',
            'email' => 'marcus5@example.com',
            'role' => 'line_manager',
            'manager_id' => $lineManager->id, // self assignment
        ];

        $response = $this->actingAsAdmin()->putJson('/api/admin/users', $payload);
        $response->assertStatus(400)
            ->assertJson(['error' => 'A user cannot be assigned as their own manager.']);
    }

    /**
     * Test 7: Circular manager reporting hierarchy is rejected.
     */
    public function test_circular_manager_hierarchy_is_rejected(): void
    {
        // Manager Mark -> Line Manager Marcus -> Line Manager Victor
        $lineManager1 = User::create([
            'emp_id' => 'EMP-LM6A',
            'username' => 'marcus_lm6a',
            'email' => 'marcus6a@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
        ]);

        $lineManager2 = User::create([
            'emp_id' => 'EMP-LM6B',
            'username' => 'victor_lm6b',
            'email' => 'victor6b@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $lineManager1->id,
        ]);

        // Attempt to assign Marcus's manager to Victor (Victor -> Marcus -> Victor)
        $payload = [
            'id' => $lineManager1->id,
            'emp_id' => 'EMP-LM6A',
            'username' => 'marcus_lm6a',
            'email' => 'marcus6a@example.com',
            'role' => 'line_manager',
            'manager_id' => $lineManager2->id,
        ];

        $response = $this->actingAsAdmin()->putJson('/api/admin/users', $payload);
        $response->assertStatus(400);
        $this->assertStringContainsString('Circular manager assignment', $response->json('error'));
    }

    /**
     * Test 8: Non-existent manager is rejected.
     */
    public function test_non_existent_manager_is_rejected(): void
    {
        $payload = [
            'emp_id' => 'EMP-NEW',
            'username' => 'new_user',
            'email' => 'new_user@example.com',
            'password' => 'password123',
            'role' => 'user',
            'manager_id' => 999999, // non-existent
        ];

        $response = $this->actingAsAdmin()->postJson('/api/admin/users', $payload);
        $response->assertStatus(400)
            ->assertJson(['error' => 'Selected manager does not exist.']);
    }

    /**
     * Test 9: Ineligible manager role (e.g. employee) is rejected.
     */
    public function test_ineligible_manager_role_is_rejected(): void
    {
        $employee = User::create([
            'emp_id' => 'EMP-SUB',
            'username' => 'sub_user',
            'email' => 'sub_user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'manager_id' => $this->manager->id,
        ]);

        $payload = [
            'emp_id' => 'EMP-NEW2',
            'username' => 'new_user2',
            'email' => 'new_user2@example.com',
            'password' => 'password123',
            'role' => 'user',
            'manager_id' => $employee->id, // Employee cannot be a manager
        ];

        $response = $this->actingAsAdmin()->postJson('/api/admin/users', $payload);
        $response->assertStatus(400)
            ->assertJson(['error' => 'Selected user is not an eligible manager.']);
    }

    /**
     * Test 10: Line Manager cannot access admin-only routes.
     */
    public function test_line_manager_cannot_access_admin_routes(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM7',
            'username' => 'marcus_lm7',
            'email' => 'marcus7@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
        ]);

        // Attempt to access /admin/users
        $resUsers = $this->actingAsUser($lineManager)->get('/admin/users');
        $resUsers->assertStatus(302); // Redirect to dashboard unauthorized

        // Attempt to call /api/admin/users
        $resApiUsers = $this->actingAsUser($lineManager)->getJson('/api/admin/users');
        $resApiUsers->assertStatus(403);

        // Attempt to call /api/admin/roles-permissions
        $resRoles = $this->actingAsUser($lineManager)->getJson('/api/admin/roles-permissions');
        $resRoles->assertStatus(403);
    }

    /**
     * Test 11: Line Manager can access team campaigns and approve/reject assigned employee campaign.
     */
    public function test_line_manager_approval_workflow(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM8',
            'username' => 'marcus_lm8',
            'email' => 'marcus8@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
            'daily_limit' => 2000,
        ]);

        $employee = User::create([
            'emp_id' => 'EMP-ABR3',
            'username' => 'abraham3',
            'email' => 'abraham3@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'manager_id' => $lineManager->id,
            'daily_limit' => 1000,
        ]);

        // Employee submits a campaign
        $campPayload = [
            'subject' => 'Team Newsletter',
            'body' => '<p>Hello team</p>',
            'sending_domain' => 'example.com',
            'from_address' => 'sender@example.com',
            'recipient_emails' => ['client@example.com'],
        ];

        $submitRes = $this->actingAsUser($employee)->postJson('/api/campaign/send', $campPayload);
        $submitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'pending_line_manager',
                'current_approver_id' => $lineManager->id,
            ]);

        $campaign = Campaign::where('user_id', $employee->id)->firstOrFail();

        // Line Manager views team campaigns
        $teamCampsRes = $this->actingAsUser($lineManager)->getJson('/api/manager/campaigns');
        $teamCampsRes->assertStatus(200);
        $this->assertTrue(collect($teamCampsRes->json())->contains('id', $campaign->id));

        // Line Manager approves campaign
        $approveRes = $this->actingAsUser($lineManager)->postJson('/api/campaign/approve', [
            'campaign_id' => $campaign->id,
            'decision' => 'approve',
            'remark' => 'Approved by Marcus',
        ]);
        $approveRes->assertStatus(200)->assertJson(['success' => true, 'new_status' => 'queued']);

        $campaign->refresh();
        $this->assertEquals('queued', $campaign->status);
        $this->assertEquals($lineManager->id, $campaign->approved_by);
    }

    /**
     * Test 12: Deleting a manager safely clears manager_id from subordinates.
     */
    public function test_deleting_manager_safely_clears_subordinate_relationships(): void
    {
        $lineManager = User::create([
            'emp_id' => 'EMP-LM9',
            'username' => 'marcus_lm9',
            'email' => 'marcus9@example.com',
            'password' => Hash::make('password123'),
            'role' => 'line_manager',
            'manager_id' => $this->manager->id,
        ]);

        $employee = User::create([
            'emp_id' => 'EMP-ABR4',
            'username' => 'abraham4',
            'email' => 'abraham4@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'manager_id' => $lineManager->id,
        ]);

        $deleteRes = $this->actingAsAdmin()->deleteJson('/api/admin/users', ['id' => $lineManager->id]);
        $deleteRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('users', ['id' => $lineManager->id]);

        $employee->refresh();
        $this->assertNull($employee->manager_id);
    }
}

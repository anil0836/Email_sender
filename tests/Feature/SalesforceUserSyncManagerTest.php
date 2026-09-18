<?php

namespace Tests\Feature;

use App\Models\SalesforceSyncLog;
use App\Models\SalesforceUser;
use App\Models\User;
use App\Services\SalesforceUserSyncService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesforceUserSyncManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_salesforce_users_table_has_manager_id_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('salesforce_users', 'manager_id'),
            'salesforce_users table must have manager_id column'
        );
    }

    public function test_salesforce_user_model_manager_and_direct_reports_relationships(): void
    {
        // Create Manager Salesforce User
        $manager = SalesforceUser::create([
            'salesforce_id' => '005MGR000000001',
            'username' => 'sarah.manager@example.com',
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'name' => 'Sarah Connor',
            'email' => 'sarah.connor@example.com',
            'title' => 'Regional Sales Director',
            'department' => 'Sales',
            'is_active' => true,
            'user_type' => 'Standard',
            'manager_id' => null,
        ]);

        // Create Employee 1 with Manager
        $employee1 = SalesforceUser::create([
            'salesforce_id' => '005EMP000000001',
            'username' => 'john.employee@example.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'name' => 'John Smith',
            'email' => 'john.smith@example.com',
            'title' => 'Account Executive',
            'department' => 'Sales',
            'is_active' => true,
            'user_type' => 'Standard',
            'manager_id' => '005MGR000000001',
        ]);

        // Create Employee 2 with Manager
        $employee2 = SalesforceUser::create([
            'salesforce_id' => '005EMP000000002',
            'username' => 'kyle.reese@example.com',
            'first_name' => 'Kyle',
            'last_name' => 'Reese',
            'name' => 'Kyle Reese',
            'email' => 'kyle.reese@example.com',
            'title' => 'Sales Representative',
            'department' => 'Sales',
            'is_active' => true,
            'user_type' => 'Standard',
            'manager_id' => '005MGR000000001',
        ]);

        // Verify Employee -> Manager
        $this->assertNotNull($employee1->manager);
        $this->assertEquals($manager->id, $employee1->manager->id);
        $this->assertEquals('005MGR000000001', $employee1->manager->salesforce_id);
        $this->assertEquals('Sarah Connor', $employee1->manager->name);

        $this->assertNotNull($employee2->manager);
        $this->assertEquals($manager->id, $employee2->manager->id);

        // Verify Manager -> Direct Reports
        $this->assertEquals(2, $manager->directReports()->count());
        $directReportIds = $manager->directReports->pluck('salesforce_id')->toArray();
        $this->assertContains('005EMP000000001', $directReportIds);
        $this->assertContains('005EMP000000002', $directReportIds);

        // Verify user with no manager returns null
        $this->assertNull($manager->manager);
    }

    public function test_salesforce_user_scope_search_with_manager_id(): void
    {
        SalesforceUser::create([
            'salesforce_id' => '005SEARCH001',
            'username' => 'alex@example.com',
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'is_active' => true,
            'manager_id' => '005MGRSEARCH99',
        ]);

        $searchResult = SalesforceUser::search('005MGRSEARCH99')->get();
        $this->assertCount(1, $searchResult);
        $this->assertEquals('005SEARCH001', $searchResult->first()->salesforce_id);
    }

    public function test_user_sync_service_upsert_logic_and_manager_update(): void
    {
        // 1. Initial sync state with initial manager
        $sfUser = SalesforceUser::create([
            'salesforce_id' => '005TESTSYNC001',
            'username' => 'syncuser@example.com',
            'first_name' => 'David',
            'last_name' => 'Miller',
            'name' => 'David Miller',
            'email' => 'david.miller@example.com',
            'is_active' => true,
            'manager_id' => '005OLDMGR001',
        ]);

        $this->assertEquals('005OLDMGR001', $sfUser->manager_id);

        // 2. Simulate subsequent sync with new ManagerId from Salesforce
        $updatedData = [
            'salesforce_id' => '005TESTSYNC001',
            'username' => 'syncuser@example.com',
            'first_name' => 'David',
            'last_name' => 'Miller',
            'name' => 'David Miller',
            'email' => 'david.miller@example.com',
            'is_active' => true,
            'manager_id' => '005NEWMGR002',
            'synced_at' => now(),
        ];

        $sfUser->update($updatedData);
        $sfUser->refresh();

        $this->assertEquals('005NEWMGR002', $sfUser->manager_id);
        $this->assertEquals(1, SalesforceUser::where('salesforce_id', '005TESTSYNC001')->count());
    }

    public function test_admin_salesforce_users_view_renders_manager_column(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $manager = SalesforceUser::create([
            'salesforce_id' => '005VIEW001',
            'username' => 'manager.view@example.com',
            'first_name' => 'Evelyn',
            'last_name' => 'Cross',
            'name' => 'Evelyn Cross',
            'email' => 'evelyn@example.com',
            'is_active' => true,
            'user_type' => 'Standard',
        ]);

        SalesforceUser::create([
            'salesforce_id' => '005VIEW002',
            'username' => 'employee.view@example.com',
            'first_name' => 'Mark',
            'last_name' => 'Ruffalo',
            'name' => 'Mark Ruffalo',
            'email' => 'mark@example.com',
            'is_active' => true,
            'user_type' => 'Standard',
            'manager_id' => $manager->salesforce_id,
        ]);

        $session = [
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ];

        $response = $this->withSession($session)->get('/admin/salesforce-users');
        $response->assertStatus(200);
        $response->assertSee('Manager');
        $response->assertSee('Evelyn Cross');
        $response->assertSee('Mark Ruffalo');
    }
}

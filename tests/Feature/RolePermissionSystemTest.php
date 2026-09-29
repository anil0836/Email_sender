<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard application data and roles/permissions
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test 1: Verify default roles, permissions, and Admin super-admin permissions.
     */
    public function test_default_roles_and_permissions_are_seeded(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
        $this->assertDatabaseHas('roles', ['name' => 'Manager']);
        $this->assertDatabaseHas('roles', ['name' => 'Employee']);

        $bulkPermissions = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'bulk-mail.manage',
        ];

        foreach ($bulkPermissions as $perm) {
            $this->assertDatabaseHas('permissions', ['name' => $perm]);
        }

        // Admin role must have all permissions
        $adminRole = Role::where('name', 'Admin')->first();
        $this->assertNotNull($adminRole);
        foreach ($bulkPermissions as $perm) {
            $this->assertTrue($adminRole->hasPermissionTo($perm), "Admin role must have permission: {$perm}");
        }
    }

    /**
     * Test 2: Verify existing users are mapped correctly to Spatie roles.
     * admin -> Admin, manager -> Manager, user -> Employee.
     */
    public function test_existing_users_are_migrated_to_proper_spatie_roles(): void
    {
        $admin = User::where('username', 'admin')->first();
        $manager = User::where('username', 'manager')->first();
        $employee = User::where('username', 'user')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($manager);
        $this->assertNotNull($employee);

        $this->assertTrue($admin->hasRole('Admin'), 'User admin must have Spatie role Admin');
        $this->assertTrue($manager->hasRole('Manager'), 'User manager must have Spatie role Manager');
        $this->assertTrue($employee->hasRole('Employee'), 'User with role user must map to Spatie role Employee');

        // Verify relationships and sensitive data were untouched
        $lineManager = User::where('username', 'linemanager')->first();
        if ($lineManager) {
            $this->assertEquals($lineManager->id, $employee->manager_id);
            $this->assertEquals($manager->id, $lineManager->manager_id);
        } else {
            $this->assertEquals($manager->id, $employee->manager_id);
        }
        $this->assertEquals('user@b2bbulkmail.com', $employee->email);
    }

    /**
     * Test 3: Admin has full access to Bulk Mail routes and Admin management UI.
     */
    public function test_admin_has_full_access_to_bulk_mail_routes_and_admin_ui(): void
    {
        $admin = User::where('username', 'admin')->first();

        $resBulk = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/campaign/bulk');
        $resBulk->assertStatus(200);

        $resCreate = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/campaign/new');
        $resCreate->assertStatus(200);

        $resRolesUi = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->get('/admin/roles-permissions');
        $resRolesUi->assertStatus(200);
        $resRolesUi->assertSee('Role-Based Access Control');

        $resRolesApi = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->getJson('/api/admin/roles-permissions');
        $resRolesApi->assertStatus(200);
        $resRolesApi->assertJsonStructure(['roles', 'all_permissions', 'grouped_permissions', 'users']);
    }

    /**
     * Test 4: Bulk Mail access is NOT tied to role; when Employee lacks bulk-mail permission, access is denied.
     */
    public function test_employee_without_bulk_mail_permission_is_denied(): void
    {
        $employee = User::where('username', 'user')->first();
        $employeeRole = Role::where('name', 'Employee')->first();

        // Ensure Employee does not have bulk-mail permission
        $employeeRole->revokePermissionTo(['bulk-mail', 'bulk-mail.view', 'bulk-mail.create', 'bulk-mail.send']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Direct web access to /campaign/bulk should be denied
        $res = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/bulk');
        $res->assertStatus(403);

        // 2. Direct web access to /campaign/new should be denied
        $resNew = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/new');
        $resNew->assertStatus(403);

        // 3. API validate should return 403
        $resVal = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->postJson('/api/campaign/validate', ['recipient_emails' => ['test@example.com']]);
        $resVal->assertStatus(403);

        // 4. API send should return 403
        $resSend = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->postJson('/api/campaign/send', [
                'subject' => 'Test',
                'body' => '<p>Hello</p>',
                'recipient_emails' => ['test@example.com'],
            ]);
        $resSend->assertStatus(403);
    }

    /**
     * Test 5: When Admin assigns bulk-mail permission to Employee, Employee can access and use Bulk Mail.
     */
    public function test_employee_with_bulk_mail_permission_is_granted_access(): void
    {
        $employee = User::where('username', 'user')->first();
        $employeeRole = Role::where('name', 'Employee')->first();

        // Grant bulk-mail permission to Employee
        $employeeRole->givePermissionTo('bulk-mail');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Employee can now access /campaign/bulk
        $resBulk = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/bulk');
        $resBulk->assertStatus(200);

        // 2. Employee can now access /campaign/new
        $resNew = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/new');
        $resNew->assertStatus(200);

        // 3. Employee can validate recipients
        $resVal = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->postJson('/api/campaign/validate', ['recipient_emails' => ['test@example.com']]);
        $resVal->assertStatus(200);
    }

    /**
     * Test 6: Manager Bulk Mail permission is configurable independently.
     */
    public function test_manager_bulk_mail_permission_is_configurable(): void
    {
        $manager = User::where('username', 'manager')->first();
        $managerRole = Role::where('name', 'Manager')->first();

        // Revoke bulk-mail if any
        $managerRole->revokePermissionTo(['bulk-mail', 'bulk-mail.view', 'bulk-mail.create', 'bulk-mail.send']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Access should be denied
        $resDenied = $this->actingAs($manager)
            ->withSession(['user_id' => $manager->id, 'username' => $manager->username, 'role' => 'manager'])
            ->get('/campaign/bulk');
        $resDenied->assertStatus(403);

        // Grant bulk-mail to Manager
        $managerRole->givePermissionTo('bulk-mail');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Access should now be granted
        $resGranted = $this->actingAs($manager)
            ->withSession(['user_id' => $manager->id, 'username' => $manager->username, 'role' => 'manager'])
            ->get('/campaign/bulk');
        $resGranted->assertStatus(200);
    }

    /**
     * Test 7: Admin can synchronize role permissions via API.
     */
    public function test_admin_can_synchronize_role_permissions_via_api(): void
    {
        $admin = User::where('username', 'admin')->first();
        $employeeRole = Role::where('name', 'Employee')->first();

        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson("/api/admin/roles/{$employeeRole->id}/permissions", [
                'permissions' => ['bulk-mail', 'signatures.manage'],
            ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $employeeRole->refresh();
        $this->assertTrue($employeeRole->hasPermissionTo('bulk-mail'));
        $this->assertTrue($employeeRole->hasPermissionTo('signatures.manage'));
        $this->assertFalse($employeeRole->hasPermissionTo('team-campaigns.view'));
    }

    /**
     * Test 8: Admin can reassign user role via API.
     */
    public function test_admin_can_reassign_user_role_via_api(): void
    {
        $admin = User::where('username', 'admin')->first();
        $user = User::where('username', 'user')->first();

        $res = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson("/api/admin/users/{$user->id}/assign-role", [
                'role' => 'Manager',
            ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $user->refresh();
        $this->assertTrue($user->hasRole('Manager'));
        $this->assertEquals('manager', $user->role);
    }

    /**
     * Test 9: Seeder idempotency - running RolePermissionSeeder repeatedly generates no duplicates.
     */
    public function test_seeder_is_idempotent(): void
    {
        $initialRoleCount = Role::count();
        $initialPermCount = Permission::count();

        // Run seeder again
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertEquals($initialRoleCount, Role::count());
        $this->assertEquals($initialPermCount, Permission::count());
    }
}

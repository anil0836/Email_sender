<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test 1: Admin can create a new permission and automatically assign it to selected roles.
     */
    public function test_admin_can_create_new_permission_and_assign_roles(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/permissions', [
                'name' => 'campaign.analytics',
                'assign_roles' => ['Admin', 'Manager'],
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => "Permission 'campaign.analytics' created successfully.",
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'campaign.analytics',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::where('name', 'Admin')->first();
        $managerRole = Role::where('name', 'Manager')->first();
        $employeeRole = Role::where('name', 'Employee')->first();

        $this->assertTrue($adminRole->hasPermissionTo('campaign.analytics'));
        $this->assertTrue($managerRole->hasPermissionTo('campaign.analytics'));
        $this->assertFalse($employeeRole->hasPermissionTo('campaign.analytics'));
    }

    /**
     * Test 2: Validation prevents invalid permission names and duplicate creation.
     */
    public function test_permission_creation_validation(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Missing name
        $resMissing = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/permissions', [
                'name' => '',
            ]);
        $resMissing->assertStatus(422);

        // 2. Invalid characters (spaces and symbols)
        $resInvalid = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/permissions', [
                'name' => 'invalid permission name with spaces!',
            ]);
        $resInvalid->assertStatus(422);

        // 3. Duplicate name
        $resDuplicate = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->postJson('/api/admin/permissions', [
                'name' => 'campaign.new',
            ]);
        $resDuplicate->assertStatus(422);
    }

    /**
     * Test 3: Non-admin users cannot create permissions.
     */
    public function test_non_admin_cannot_create_permissions(): void
    {
        $employee = User::where('username', 'user')->first();
        $manager = User::where('username', 'manager')->first();

        // Employee attempt
        $resEmployee = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->postJson('/api/admin/permissions', [
                'name' => 'test.forbidden',
            ]);
        $resEmployee->assertStatus(403);

        // Manager attempt
        $resManager = $this->actingAs($manager)
            ->withSession(['user_id' => $manager->id, 'username' => $manager->username, 'role' => 'manager'])
            ->postJson('/api/admin/permissions', [
                'name' => 'test.forbidden',
            ]);
        $resManager->assertStatus(403);
    }

    /**
     * Test 4: Custom permissions can be deleted, but core permissions are protected.
     */
    public function test_custom_permissions_can_be_deleted_and_core_permissions_protected(): void
    {
        $admin = User::where('username', 'admin')->first();

        // 1. Create a custom permission
        $customPerm = Permission::create(['name' => 'custom.deletable.perm', 'guard_name' => 'web']);

        // Delete custom permission
        $resDelete = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->deleteJson("/api/admin/permissions/{$customPerm->id}");

        $resDelete->assertStatus(200);
        $resDelete->assertJson(['success' => true]);
        $this->assertDatabaseMissing('permissions', ['name' => 'custom.deletable.perm']);

        // 2. Attempt to delete core permission (bulk-mail)
        $corePerm = Permission::where('name', 'bulk-mail')->first();
        $resCore = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->deleteJson("/api/admin/permissions/{$corePerm->id}");

        $resCore->assertStatus(403);
        $resCore->assertJson([
            'success' => false,
            'message' => 'Core system permissions cannot be deleted.',
        ]);
        $this->assertDatabaseHas('permissions', ['name' => 'bulk-mail']);
    }

    /**
     * Test 5: Granular access control for /campaign/new with path notation (/campaign/new)
     * and dot notation (campaign.new, campaign.create).
     */
    public function test_campaign_new_route_with_specific_permissions(): void
    {
        $employee = User::where('username', 'user')->first();
        $employeeRole = Role::where('name', 'Employee')->first();

        // Revoke all bulk-mail permissions from Employee
        $employeeRole->revokePermissionTo([
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
        ]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Baseline: Employee cannot access /campaign/new
        $resDenied = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/new');
        $resDenied->assertStatus(403);

        // Case A: Give Employee specifically '/campaign/new' permission
        $employeeRole->givePermissionTo('/campaign/new');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $resPath = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/new');
        $resPath->assertStatus(200);

        // Revoke '/campaign/new'
        $employeeRole->revokePermissionTo('/campaign/new');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Case B: Give Employee specifically 'campaign.new' permission
        $employeeRole->givePermissionTo('campaign.new');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $resDot = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/new');
        $resDot->assertStatus(200);

        // Revoke 'campaign.new'
        $employeeRole->revokePermissionTo('campaign.new');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Case C: Give Employee specifically 'campaign.create' permission
        $employeeRole->givePermissionTo('campaign.create');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $resCreate = $this->actingAs($employee)
            ->withSession(['user_id' => $employee->id, 'username' => $employee->username, 'role' => 'user'])
            ->get('/campaign/new');
        $resCreate->assertStatus(200);
    }

    /**
     * Test 6: API returns grouped permissions including campaign_bulk and campaign permissions.
     */
    public function test_api_returns_campaign_permissions_in_catalog(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)
            ->withSession(['user_id' => $admin->id, 'username' => $admin->username, 'role' => 'admin'])
            ->getJson('/api/admin/roles-permissions');

        $response->assertStatus(200);

        $permissionNames = collect($response->json('all_permissions'))->pluck('name')->all();

        $this->assertContains('/campaign/new', $permissionNames);
        $this->assertContains('campaign.new', $permissionNames);
        $this->assertContains('campaign.create', $permissionNames);

        $grouped = $response->json('grouped_permissions');
        $this->assertArrayHasKey('campaign_bulk', $grouped);
    }
}

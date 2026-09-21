<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web';

        // 2. Define standard application permissions
        $permissions = [
            // Bulk Mail Permissions
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'bulk-mail.manage',

            // Campaign Routing & Creation Permissions
            'campaign.new',
            '/campaign/new',
            'campaign.create',

            // Application Feature Permissions
            'signatures.manage',
            'templates.manage',
            'inbound-replies.view',
            'team-campaigns.view',
            'users.manage',
            'infrastructure.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => $guardName]
            );
        }

        // 3. Define and create default roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => $guardName]);
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => $guardName]);

        // 4. Admin receives ALL permissions
        $allPermissions = Permission::where('guard_name', $guardName)->get();
        $adminRole->syncPermissions($allPermissions);

        // 5. Default baseline permissions for Manager & Employee (fully customizable/revocable by Admin)
        // Manager by default has bulk-mail, team campaigns, signatures, and inbound replies
        if ($managerRole->permissions()->count() === 0) {
            $managerRole->givePermissionTo([
                'bulk-mail',
                'bulk-mail.view',
                'bulk-mail.create',
                'bulk-mail.send',
                'team-campaigns.view',
                'signatures.manage',
                'templates.manage',
                'inbound-replies.view',
            ]);
        }

        // Employee by default has bulk-mail, signatures, and templates (Bulk mail is configurable & revocable by Admin!)
        if ($employeeRole->permissions()->count() === 0) {
            $employeeRole->givePermissionTo([
                'bulk-mail',
                'bulk-mail.view',
                'bulk-mail.create',
                'bulk-mail.send',
                'signatures.manage',
                'templates.manage',
            ]);
        }

        // 6. Safely migrate existing users to Spatie roles without breaking data
        // admin -> Admin
        // manager -> Manager
        // user -> Employee
        $users = User::all();
        foreach ($users as $user) {
            $existingRole = strtolower(trim((string) $user->role));

            if ($existingRole === 'admin') {
                $user->syncRoles(['Admin']);
            } elseif ($existingRole === 'manager') {
                $user->syncRoles(['Manager']);
            } elseif ($existingRole === 'user' || $existingRole === 'employee' || empty($existingRole)) {
                $user->syncRoles(['Employee']);
            } else {
                // If a user has a custom role name, capitalize it or fallback to Employee
                $roleObj = Role::where('name', ucfirst($existingRole))->first();
                if ($roleObj) {
                    $user->syncRoles([$roleObj->name]);
                } else {
                    $user->syncRoles(['Employee']);
                }
            }
        }

        // 7. Clear cache again to guarantee immediate availability
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

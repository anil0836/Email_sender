<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web';

        // 1. Ensure core roles exist
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => $guardName]);
        $lineManagerRole = Role::firstOrCreate(['name' => 'Line Manager', 'guard_name' => $guardName]);
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => $guardName]);

        // 2. Define standard application permissions
        $permissions = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'bulk-mail.manage',
            'campaign.new',
            '/campaign/new',
            'campaign.create',
            'signatures.manage',
            'templates.manage',
            'inbound-replies.view',
            'team-campaigns.view',
            'users.manage',
            'infrastructure.manage',
            'email-suppressions.view',
            'email-suppressions.create',
            'email-suppressions.import',
            'email-suppressions.resubscribe',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => $guardName,
            ]);
        }

        // 3. Admin receives all permissions
        $allPermissions = Permission::where('guard_name', $guardName)->get();
        $adminRole->syncPermissions($allPermissions);

        // 4. Manager & Line Manager baseline permissions
        $managerPermissions = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'team-campaigns.view',
            'signatures.manage',
            'templates.manage',
            'inbound-replies.view',
        ];
        $managerRole->givePermissionTo($managerPermissions);
        $lineManagerRole->givePermissionTo($managerPermissions);

        // If legacy role named 'line_manager' exists, also grant baseline permissions
        $legacyRole = Role::where('name', 'line_manager')->where('guard_name', $guardName)->first();
        if ($legacyRole) {
            $legacyRole->givePermissionTo($managerPermissions);
        }

        // 5. Employee baseline permissions
        $employeePermissions = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'signatures.manage',
            'templates.manage',
        ];
        $employeeRole->givePermissionTo($employeePermissions);

        // 4. Synchronize all users with role 'line_manager' to Spatie 'Line Manager' role
        $users = User::where('role', 'line_manager')->get();
        foreach ($users as $user) {
            if (!$user->hasRole('Line Manager')) {
                $user->assignRole('Line Manager');
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Preserved for safety to prevent accidental loss of user permissions
    }
};

<?php

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
        $permissionsToAdd = [
            'campaign.new',
            '/campaign/new',
            'campaign.create',
        ];

        foreach ($permissionsToAdd as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => $guardName,
            ]);
        }

        // Grant newly created permissions to Admin role
        $adminRole = Role::where('name', 'Admin')->where('guard_name', $guardName)->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissionsToAdd);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web';
        $permissionsToRemove = [
            'campaign.new',
            '/campaign/new',
            'campaign.create',
        ];

        Permission::whereIn('name', $permissionsToRemove)
            ->where('guard_name', $guardName)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

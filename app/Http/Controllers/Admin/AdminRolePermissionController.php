<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRolePermissionController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display the Role & Permission Management View.
     */
    public function index(Request $request)
    {
        return view('admin.roles');
    }

    /**
     * Get JSON data for roles, permissions matrix, and users.
     */
    public function apiData(Request $request)
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        // Categorize permissions for clean UI presentation
        $groupedPermissions = [
            'campaign_bulk' => [
                'label' => 'Campaign & Bulk Mail Capabilities',
                'description' => 'Permissions controlling access, creation, and sending of email campaigns and /campaign/new',
                'items' => $permissions->filter(fn($p) => str_starts_with($p->name, 'bulk-mail') || str_starts_with($p->name, 'campaign') || str_starts_with($p->name, '/campaign'))->values(),
            ],
            'features' => [
                'label' => 'Application Features',
                'description' => 'Permissions governing access to specific platform tools and management areas',
                'items' => $permissions->filter(fn($p) => !str_starts_with($p->name, 'bulk-mail') && !str_starts_with($p->name, 'campaign') && !str_starts_with($p->name, '/campaign'))->values(),
            ],
        ];

        $users = User::with('roles')
            ->select('id', 'name', 'username', 'email', 'role', 'is_blocked', 'created_at')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'legacy_role' => $u->role,
                    'spatie_role' => $u->roles->first()?->name ?? 'None',
                    'all_spatie_roles' => $u->roles->pluck('name'),
                    'is_blocked' => (bool) $u->is_blocked,
                ];
            });

        return response()->json([
            'roles' => $roles,
            'all_permissions' => $permissions,
            'grouped_permissions' => $groupedPermissions,
            'users' => $users,
        ]);
    }

    /**
     * Synchronize permissions for a specific role.
     */
    public function syncRolePermissions(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);
        $permissions = $request->input('permissions', []);

        if (!is_array($permissions)) {
            return response()->json(['error' => 'Permissions must be provided as an array.'], 400);
        }

        // Validate that all permissions exist
        $validPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();

        // Sync permissions
        $role->syncPermissions($validPermissions);

        // Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Audit the change
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin) {
            $this->auditService->logActivity(
                $admin->id,
                'Update Role Permissions',
                "Updated permissions for role '{$role->name}'. Granted: " . implode(', ', $validPermissions),
                $request->ip()
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'role' => $role->fresh('permissions'),
        ]);
    }

    /**
     * Create a new role.
     */
    public function createRole(Request $request)
    {
        $name = trim((string) $request->input('name'));

        if (empty($name)) {
            return response()->json(['error' => 'Role name is required.'], 400);
        }

        if (Role::where('name', $name)->exists()) {
            return response()->json(['error' => "Role '{$name}' already exists."], 400);
        }

        $role = Role::create(['name' => $name, 'guard_name' => 'web']);

        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin) {
            $this->auditService->logActivity(
                $admin->id,
                'Create Role',
                "Created new application role '{$name}'",
                $request->ip()
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Role '{$name}' created successfully.",
            'role' => $role,
        ]);
    }

    /**
     * Assign or change a user's Spatie role.
     */
    public function assignUserRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $roleName = $request->input('role');

        if (empty($roleName)) {
            return response()->json(['error' => 'Role name is required.'], 400);
        }

        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return response()->json(['error' => "Role '{$roleName}' does not exist."], 400);
        }

        // Sync Spatie role
        $user->syncRoles([$role->name]);

        // Keep legacy role column synchronized
        $legacyRole = match ($role->name) {
            'Admin' => 'admin',
            'Manager' => 'manager',
            'Employee' => 'user',
            default => strtolower($role->name),
        };
        $user->role = $legacyRole;
        $user->save();

        // Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Audit the change
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin) {
            $this->auditService->logActivity(
                $admin->id,
                'Assign User Role',
                "Assigned role '{$role->name}' to user {$user->username} (ID: {$user->id})",
                $request->ip()
            );
        }

        return response()->json([
            'success' => true,
            'message' => "User '{$user->username}' role updated to '{$role->name}'.",
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'legacy_role' => $user->role,
                'spatie_role' => $role->name,
            ],
        ]);
    }

    /**
     * Create a new permission.
     */
    public function createPermission(Request $request)
    {
        $name = trim((string) $request->input('name'));

        if (empty($name)) {
            return response()->json(['success' => false, 'error' => 'Permission name is required.', 'message' => 'Permission name is required.'], 422);
        }

        // Basic character validation: alphanumeric, dots, hyphens, slashes, underscores
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $name)) {
            return response()->json(['success' => false, 'error' => 'Permission name can only contain letters, numbers, dots, dashes, slashes, and underscores.', 'message' => 'Permission name format is invalid.'], 422);
        }

        if (Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
            return response()->json(['success' => false, 'error' => "Permission '{$name}' already exists.", 'message' => "Permission '{$name}' already exists."], 422);
        }

        $permission = Permission::create(['name' => $name, 'guard_name' => 'web']);

        // By default, grant new permissions to the Admin role
        $adminRole = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permission);
        }

        // If requested to grant to other roles (e.g. Manager or Employee)
        $roleNames = $request->input('roles', $request->input('assign_roles', []));
        if (is_array($roleNames) && !empty($roleNames)) {
            foreach ($roleNames as $rName) {
                $r = Role::where('name', $rName)->where('guard_name', 'web')->first();
                if ($r && $r->name !== 'Admin') {
                    $r->givePermissionTo($permission);
                }
            }
        }

        // Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Audit the change
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin) {
            $this->auditService->logActivity(
                $admin->id,
                'Create Permission',
                "Created new application permission '{$name}'",
                $request->ip()
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Permission '{$name}' created successfully.",
            'permission' => $permission,
        ], 201);
    }

    /**
     * Delete a custom permission.
     */
    public function deletePermission(Request $request, $permissionId)
    {
        $permission = Permission::findOrFail($permissionId);

        // Protect core system permissions from accidental deletion
        $protected = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'bulk-mail.manage',
            'users.manage',
            'infrastructure.manage',
        ];

        if (in_array($permission->name, $protected)) {
            return response()->json([
                'success' => false,
                'error' => "System core permission '{$permission->name}' cannot be deleted.",
                'message' => 'Core system permissions cannot be deleted.',
            ], 403);
        }

        $permName = $permission->name;
        $permission->delete();

        // Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin) {
            $this->auditService->logActivity(
                $admin->id,
                'Delete Permission',
                "Deleted application permission '{$permName}'",
                $request->ip()
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Permission '{$permName}' deleted successfully.",
        ]);
    }

    /**
     * Reset Spatie's permission cache.
     */
    public function resetCache(Request $request)
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permission cache flushed successfully.',
        ]);
    }
}

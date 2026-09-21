<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CrmSetting;
use App\Models\SendingDomain;
use App\Models\Server;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Show Users Management View.
     */
    public function usersView()
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if ($user->role !== 'admin') {
            return redirect()->route('dashboard_view')->with('danger', 'Unauthorized access.');
        }

        return view('admin.users');
    }

    /**
     * Show Infrastructure View.
     */
    public function infrastructureView()
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if ($user->role !== 'admin') {
            return redirect()->route('dashboard_view')->with('danger', 'Unauthorized access.');
        }

        return view('admin.servers');
    }

    /**
     * Users CRUD API.
     */
    public function apiUsers(Request $request)
    {
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->isMethod('get')) {
            $users = User::with(['roles', 'manager', 'assignedServer', 'assignedDomain'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'emp_id' => $u->emp_id,
                        'username' => $u->username,
                        'name' => $u->name,
                        'email' => $u->email,
                        'role' => $u->role,
                        'spatie_role' => $u->roles->first()?->name ?? ($u->role === 'user' ? 'Employee' : ucfirst($u->role)),
                        'manager_id' => $u->manager_id,
                        'is_blocked' => $u->is_blocked,
                        'manager_username' => $u->manager?->username,
                        'manager_name' => $u->manager?->name,
                        'manager_email' => $u->manager?->email,
                        'created_at' => $u->created_at,
                        'assigned_server_id' => $u->assigned_server_id,
                        'assigned_domain_id' => $u->assigned_domain_id,
                        'server_name' => $u->assignedServer?->name,
                        'domain_name' => $u->assignedDomain?->domain_name,
                        'daily_limit' => $u->daily_limit,
                    ];
                });

            return response()->json($users);
        }

        if ($request->isMethod('post')) {
            $empId = $request->input('emp_id');
            $username = $request->input('username');
            $email = $request->input('email');
            $password = $request->input('password');
            $roleInput = $request->input('role', 'Employee');
            
            // Normalize role
            $spatieRole = match (strtolower(trim($roleInput))) {
                'admin' => 'Admin',
                'manager' => 'Manager',
                'user', 'employee' => 'Employee',
                default => 'Employee',
            };
            $legacyRole = match ($spatieRole) {
                'Admin' => 'admin',
                'Manager' => 'manager',
                default => 'user',
            };

            $managerId = $request->input('manager_id') ?: null;
            $assignedServerId = $request->input('assigned_server_id') ?: null;
            $assignedDomainId = $request->input('assigned_domain_id') ?: null;
            $dailyLimit = (int) $request->input('daily_limit', 1000);

            if (!$username || !$password || !$email) {
                return response()->json(['error' => 'Username, password, and email address are required'], 400);
            }

            if (($legacyRole === 'user' || $spatieRole === 'Employee') && !$managerId) {
                return response()->json(['error' => 'Manager selection is required for employee role.'], 400);
            }

            if (User::where('email', $email)->exists()) {
                return response()->json(['error' => 'Email address already registered.'], 400);
            }

            if (User::where('username', $username)->exists()) {
                return response()->json(['error' => 'Username already exists.'], 400);
            }

            $newUser = User::create([
                'emp_id' => $empId,
                'username' => $username,
                'name' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $legacyRole,
                'manager_id' => $managerId,
                'assigned_server_id' => $assignedServerId,
                'assigned_domain_id' => $assignedDomainId,
                'is_blocked' => false,
                'daily_limit' => $dailyLimit,
            ]);

            // Assign Spatie Role
            $newUser->assignRole($spatieRole);

            $this->auditService->logActivity(
                $admin->id,
                'Create User',
                "Created user {$username} ({$spatieRole}) with daily limit {$dailyLimit}",
                $request->ip()
            );

            return response()->json(['success' => true, 'message' => 'User created successfully.']);
        }

        if ($request->isMethod('put')) {
            $userId = $request->input('id');
            if (!$userId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            $empId = $request->input('emp_id');
            $username = $request->input('username');
            $email = $request->input('email');
            $role = $request->input('role');
            $managerId = $request->input('manager_id') ?: null;
            $assignedServerId = $request->input('assigned_server_id') ?: null;
            $assignedDomainId = $request->input('assigned_domain_id') ?: null;
            $dailyLimit = (int) $request->input('daily_limit', 1000);
            $isBlocked = (bool) $request->input('is_blocked', false);
            $password = $request->input('password');

            if (!$username || !$email) {
                return response()->json(['error' => 'Username and Email are required.'], 400);
            }

            if ($role === 'user' && !$managerId) {
                return response()->json(['error' => 'Manager selection is required for user role.'], 400);
            }

            if (User::where('email', $email)->where('id', '!=', $userId)->exists()) {
                return response()->json(['error' => 'Email address already registered by another user.'], 400);
            }

            if (User::where('username', $username)->where('id', '!=', $userId)->exists()) {
                return response()->json(['error' => 'Username already exists.'], 400);
            }

            if (!empty($role)) {
                $spatieRole = match (strtolower(trim($role))) {
                    'admin' => 'Admin',
                    'manager' => 'Manager',
                    'user', 'employee' => 'Employee',
                    default => 'Employee',
                };
                $legacyRole = match ($spatieRole) {
                    'Admin' => 'admin',
                    'Manager' => 'manager',
                    default => 'user',
                };
                $user->role = $legacyRole;
                $user->syncRoles([$spatieRole]);
            }

            $user->emp_id = $empId;
            $user->username = $username;
            $user->name = $username;
            $user->email = $email;
            $user->manager_id = $managerId;
            $user->assigned_server_id = $assignedServerId;
            $user->assigned_domain_id = $assignedDomainId;
            $user->daily_limit = $dailyLimit;
            $user->is_blocked = $isBlocked;

            if (!empty($password)) {
                $user->password = Hash::make($password);
            }

            $user->save();

            $this->auditService->logActivity(
                $admin->id,
                'Update User',
                "Updated user ID {$userId} ({$username}, role {$role}, daily limit {$dailyLimit}, blocked: " . ($isBlocked ? '1' : '0') . ')',
                $request->ip()
            );

            return response()->json(['success' => true, 'message' => 'User updated successfully.']);
        }

        if ($request->isMethod('delete')) {
            $userId = $request->input('id');
            if (!$userId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            if ((int) $userId === (int) $admin->id) {
                return response()->json(['error' => 'You cannot delete yourself.'], 400);
            }

            User::destroy($userId);

            $this->auditService->logActivity(
                $admin->id,
                'Delete User',
                "Deleted user ID {$userId}",
                $request->ip()
            );

            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Managers dropdown list for user creation/editing.
     */
    public function apiManagers()
    {
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $managers = User::whereIn('role', ['manager', 'admin'])
            ->select('id', 'username', 'name', 'email', 'role')
            ->orderBy('name', 'asc')
            ->orderBy('username', 'asc')
            ->get();

        return response()->json($managers);
    }

    /**
     * Audit logs JSON API.
     */
    public function apiAuditLogs()
    {
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $logs = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
            ->select('a.id', 'a.user_id', 'a.action', 'a.details', 'a.ip_address', 'a.created_at', 'u.username')
            ->orderBy('a.created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json($logs);
    }

    /**
     * SMTP Servers CRUD API.
     */
    public function apiServers(Request $request)
    {
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->isMethod('get')) {
            return response()->json(Server::all());
        }

        if ($request->isMethod('post')) {
            $name = $request->input('name');
            $host = $request->input('host');
            $port = $request->input('port');
            $username = $request->input('username');
            $password = $request->input('password');
            $sendingIp = $request->input('sending_ip');
            $isActive = (bool) $request->input('is_active', true);

            if (!$name || !$host || !$port || !$sendingIp) {
                return response()->json(['error' => 'Required fields missing'], 400);
            }

            $server = Server::create([
                'name' => $name,
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'sending_ip' => $sendingIp,
                'is_active' => $isActive,
            ]);

            return response()->json([
                'success' => true,
                'server_id' => $server->id,
                'message' => 'Server/IP added successfully.',
            ]);
        }

        if ($request->isMethod('put')) {
            $serverId = $request->input('id');
            $server = Server::find($serverId);
            if (!$server) {
                return response()->json(['error' => 'Server not found'], 404);
            }

            $name = $request->input('name');
            $host = $request->input('host');
            $port = $request->input('port');
            $username = $request->input('username');
            $password = $request->input('password');
            $sendingIp = $request->input('sending_ip');
            $isActive = (bool) $request->input('is_active', true);

            if (!$name || !$host || !$port || !$sendingIp) {
                return response()->json(['error' => 'Required fields missing'], 400);
            }

            $server->update([
                'name' => $name,
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'sending_ip' => $sendingIp,
                'is_active' => $isActive,
            ]);

            return response()->json(['success' => true, 'message' => 'Server/IP updated successfully.']);
        }

        if ($request->isMethod('delete')) {
            $serverId = $request->input('id');
            Server::destroy($serverId);
            return response()->json(['success' => true, 'message' => 'Server/IP deleted successfully.']);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Sending Domains CRUD API.
     */
    public function apiDomains(Request $request)
    {
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->isMethod('get')) {
            $domains = DB::table('sending_domains as d')
                ->leftJoin('servers as s', 'd.server_id', '=', 's.id')
                ->select('d.*', 's.name as server_name')
                ->get();

            return response()->json($domains);
        }

        if ($request->isMethod('post')) {
            $domainName = $request->input('domain_name');
            $status = $request->input('status', 'enabled');
            $spfStatus = $request->input('spf_status', 'unverified');
            $dkimStatus = $request->input('dkim_status', 'unverified');
            $dmarcStatus = $request->input('dmarc_status', 'unverified');
            $serverId = $request->input('server_id') ?: null;
            $isDefault = (bool) $request->input('is_default', false);
            $rateLimit = (int) $request->input('rate_limit_per_hour', 1000);

            if (!$domainName || !$serverId) {
                return response()->json(['error' => 'Domain name and server mapping required'], 400);
            }

            if (SendingDomain::where('domain_name', $domainName)->exists()) {
                return response()->json(['error' => 'Domain name already exists.'], 400);
            }

            if ($isDefault) {
                SendingDomain::where('is_default', true)->update(['is_default' => false]);
            }

            $domain = SendingDomain::create([
                'domain_name' => $domainName,
                'status' => $status,
                'spf_status' => $spfStatus,
                'dkim_status' => $dkimStatus,
                'dmarc_status' => $dmarcStatus,
                'server_id' => $serverId,
                'is_default' => $isDefault,
                'rate_limit_per_hour' => $rateLimit,
            ]);

            return response()->json([
                'success' => true,
                'domain_id' => $domain->id,
                'message' => 'Domain added successfully.',
            ]);
        }

        if ($request->isMethod('put')) {
            $domainId = $request->input('id');
            $domain = SendingDomain::find($domainId);
            if (!$domain) {
                return response()->json(['error' => 'Domain not found'], 404);
            }

            $domainName = $request->input('domain_name');
            $status = $request->input('status');
            $spfStatus = $request->input('spf_status');
            $dkimStatus = $request->input('dkim_status');
            $dmarcStatus = $request->input('dmarc_status');
            $serverId = $request->input('server_id') ?: null;
            $isDefault = (bool) $request->input('is_default', false);
            $rateLimit = (int) $request->input('rate_limit_per_hour', 1000);

            if (!$domainName || !$serverId) {
                return response()->json(['error' => 'Required fields missing'], 400);
            }

            if ($isDefault) {
                SendingDomain::where('is_default', true)->update(['is_default' => false]);
            }

            $domain->update([
                'domain_name' => $domainName,
                'status' => $status,
                'spf_status' => $spfStatus,
                'dkim_status' => $dkimStatus,
                'dmarc_status' => $dmarcStatus,
                'server_id' => $serverId,
                'is_default' => $isDefault,
                'rate_limit_per_hour' => $rateLimit,
            ]);

            return response()->json(['success' => true, 'message' => 'Domain updated successfully.']);
        }

        if ($request->isMethod('delete')) {
            $domainId = $request->input('id');
            SendingDomain::destroy($domainId);
            return response()->json(['success' => true, 'message' => 'Domain deleted successfully.']);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Salesforce CRM Settings API.
     */
    public function apiCrmSettings(Request $request)
    {
        $admin = Auth::user() ?: User::find(session('user_id'));
        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->isMethod('get')) {
            $settings = CrmSetting::first();
            if (!$settings) {
                return response()->json([
                    'salesforce_client_id' => '',
                    'salesforce_client_secret' => '',
                    'salesforce_login_url' => 'https://login.salesforce.com',
                    'salesforce_username' => '',
                    'salesforce_token_or_password' => '',
                    'is_mock' => true,
                ]);
            }

            $data = $settings->toArray();
            if (!empty($data['salesforce_client_secret'])) {
                $data['salesforce_client_secret'] = '••••••••••••••••';
            }
            if (!empty($data['salesforce_token_or_password'])) {
                $data['salesforce_token_or_password'] = '••••••••••••••••';
            }

            return response()->json($data);
        }

        if ($request->isMethod('post')) {
            $clientId = $request->input('salesforce_client_id');
            $clientSecret = $request->input('salesforce_client_secret');
            $loginUrl = $request->input('salesforce_login_url', 'https://login.salesforce.com');
            $username = $request->input('salesforce_username');
            $tokenOrPassword = $request->input('salesforce_token_or_password');
            $isMock = (bool) $request->input('is_mock', true);

            $existing = CrmSetting::first();

            if ($existing) {
                if ($clientSecret === '••••••••••••••••') {
                    $clientSecret = $existing->salesforce_client_secret;
                }
                if ($tokenOrPassword === '••••••••••••••••') {
                    $tokenOrPassword = $existing->salesforce_token_or_password;
                }

                $existing->update([
                    'salesforce_client_id' => $clientId,
                    'salesforce_client_secret' => $clientSecret,
                    'salesforce_login_url' => $loginUrl,
                    'salesforce_username' => $username,
                    'salesforce_token_or_password' => $tokenOrPassword,
                    'is_mock' => $isMock,
                ]);
            } else {
                CrmSetting::create([
                    'salesforce_client_id' => $clientId,
                    'salesforce_client_secret' => $clientSecret,
                    'salesforce_login_url' => $loginUrl,
                    'salesforce_username' => $username,
                    'salesforce_token_or_password' => $tokenOrPassword,
                    'is_mock' => $isMock,
                ]);
            }

            return response()->json(['success' => true, 'message' => 'CRM settings updated successfully.']);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }
}

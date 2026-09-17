<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesforceSyncLog;
use App\Models\SalesforceUser;
use App\Services\SalesforceUserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesforceUserAdminController extends Controller
{
    /**
     * Display the Admin panel Salesforce Users directory with search and filters.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $userType = $request->input('user_type');
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = SalesforceUser::query()
            ->with(['manager'])
            ->withCount(['leads', 'accounts', 'contacts'])
            ->search($search)
            ->filterActive($isActive)
            ->filterUserType($userType);

        $users = $query->orderBy('name', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        $totalLocalUsers = SalesforceUser::count();

        $userTypes = SalesforceUser::whereNotNull('user_type')
            ->where('user_type', '!=', '')
            ->distinct()
            ->orderBy('user_type')
            ->pluck('user_type')
            ->toArray();

        $latestSync = SalesforceSyncLog::where('object_type', 'User')
            ->orderByDesc('started_at')
            ->first();

        return view('admin.salesforce-users.index', [
            'users' => $users,
            'totalLocalUsers' => $totalLocalUsers,
            'userTypes' => $userTypes,
            'latestSync' => $latestSync,
            'search' => $search,
            'selectedActive' => $isActive,
            'selectedUserType' => $userType,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Trigger manual Salesforce User synchronization from the Admin Panel.
     */
    public function manualSync(Request $request, SalesforceUserSyncService $syncService): RedirectResponse|JsonResponse
    {
        $forceFull = $request->boolean('full', false);
        $result = $syncService->syncUsers($forceFull, 'manual');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('admin.salesforce_users.index')
                ->with('success', $result['message'] ?? 'Salesforce users synchronized successfully.');
        }

        return redirect()->route('admin.salesforce_users.index')
            ->with('danger', 'Salesforce sync failed: ' . ($result['error'] ?? 'Unknown error.'));
    }

    /**
     * API endpoint to get latest synchronization status and stats for Users.
     */
    public function syncStatus(): JsonResponse
    {
        $latestSync = SalesforceSyncLog::where('object_type', 'User')
            ->orderByDesc('started_at')
            ->first();
        $totalLocalUsers = SalesforceUser::count();

        return response()->json([
            'latest_sync' => $latestSync,
            'total_local_users' => $totalLocalUsers,
        ]);
    }
}

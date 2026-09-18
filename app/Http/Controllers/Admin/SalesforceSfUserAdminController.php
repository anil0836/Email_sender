<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceSyncLog;
use App\Services\SalesforceSfUserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesforceSfUserAdminController extends Controller
{
    /**
     * Display the Admin panel Salesforce Custom Users (SF_User__c) directory with search and filters.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $process = $request->input('process');
        $team = $request->input('team');
        $location = $request->input('location');
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = SalesforceSfUser::query()
            ->with(['standardUser'])
            ->withCount(['primeLeads', 'primeAccounts', 'primeContacts'])
            ->search($search)
            ->filterActive($isActive)
            ->filterProcess($process)
            ->filterTeam($team)
            ->filterLocation($location);

        $sfUsers = $query->orderBy('name', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        $totalLocalSfUsers = SalesforceSfUser::count();

        $processes = SalesforceSfUser::whereNotNull('process')
            ->where('process', '!=', '')
            ->distinct()
            ->orderBy('process')
            ->pluck('process')
            ->toArray();

        $teams = SalesforceSfUser::whereNotNull('team')
            ->where('team', '!=', '')
            ->distinct()
            ->orderBy('team')
            ->pluck('team')
            ->toArray();

        $locations = SalesforceSfUser::whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->toArray();

        $latestSync = SalesforceSyncLog::where('object_type', 'SF_User__c')
            ->orderByDesc('started_at')
            ->first();

        return view('admin.salesforce-sf-users.index', [
            'sfUsers' => $sfUsers,
            'totalLocalSfUsers' => $totalLocalSfUsers,
            'processes' => $processes,
            'teams' => $teams,
            'locations' => $locations,
            'latestSync' => $latestSync,
            'search' => $search,
            'selectedActive' => $isActive,
            'selectedProcess' => $process,
            'selectedTeam' => $team,
            'selectedLocation' => $location,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Trigger manual Salesforce Custom User (SF_User__c) synchronization from the Admin Panel.
     */
    public function manualSync(Request $request, SalesforceSfUserSyncService $syncService): RedirectResponse|JsonResponse
    {
        $forceFull = $request->boolean('full', false);
        $result = $syncService->syncSfUsers($forceFull, 'manual');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('admin.salesforce_sf_users.index')
                ->with('success', $result['message'] ?? 'Salesforce SF Users (SF_User__c) synchronized successfully.');
        }

        return redirect()->route('admin.salesforce_sf_users.index')
            ->with('danger', 'Salesforce sync failed: ' . ($result['error'] ?? 'Unknown error.'));
    }

    /**
     * API endpoint to get latest synchronization status and stats for SF_User__c.
     */
    public function syncStatus(): JsonResponse
    {
        $latestSync = SalesforceSyncLog::where('object_type', 'SF_User__c')
            ->orderByDesc('started_at')
            ->first();
        $totalLocalSfUsers = SalesforceSfUser::count();

        return response()->json([
            'latest_sync' => $latestSync,
            'total_local_sf_users' => $totalLocalSfUsers,
        ]);
    }
}

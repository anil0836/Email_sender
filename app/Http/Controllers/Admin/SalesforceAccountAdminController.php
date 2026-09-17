<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesforceAccount;
use App\Models\SalesforceSyncLog;
use App\Services\SalesforceAccountSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesforceAccountAdminController extends Controller
{
    /**
     * Display the Admin panel Salesforce Accounts directory with search and filters.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $type = $request->input('type');
        $industry = $request->input('industry');
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = SalesforceAccount::query()
            ->withCount('contacts')
            ->with(['owner', 'primeOwner'])
            ->search($search)
            ->filterType($type)
            ->filterIndustry($industry);

        $accounts = $query->orderBy('salesforce_updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $totalLocalAccounts = SalesforceAccount::count();

        $types = SalesforceAccount::whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->toArray();

        $industries = SalesforceAccount::whereNotNull('industry')
            ->where('industry', '!=', '')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->toArray();

        $latestSync = SalesforceSyncLog::where('object_type', 'Account')
            ->orderByDesc('started_at')
            ->first();

        return view('admin.salesforce-accounts.index', [
            'accounts' => $accounts,
            'totalLocalAccounts' => $totalLocalAccounts,
            'types' => $types,
            'industries' => $industries,
            'latestSync' => $latestSync,
            'search' => $search,
            'selectedType' => $type,
            'selectedIndustry' => $industry,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Trigger manual Salesforce Account synchronization from the Admin Panel.
     */
    public function manualSync(Request $request, SalesforceAccountSyncService $syncService): RedirectResponse|JsonResponse
    {
        $forceFull = $request->boolean('full', false);
        $result = $syncService->syncAccounts($forceFull, 'manual');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('admin.salesforce_accounts.index')
                ->with('success', $result['message'] ?? 'Salesforce accounts synchronized successfully.');
        }

        return redirect()->route('admin.salesforce_accounts.index')
            ->with('danger', 'Salesforce sync failed: ' . ($result['error'] ?? 'Unknown error.'));
    }

    /**
     * API endpoint to get latest synchronization status and stats for Accounts.
     */
    public function syncStatus(): JsonResponse
    {
        $latestSync = SalesforceSyncLog::where('object_type', 'Account')
            ->orderByDesc('started_at')
            ->first();
        $totalLocalAccounts = SalesforceAccount::count();

        return response()->json([
            'latest_sync' => $latestSync,
            'total_local_accounts' => $totalLocalAccounts,
        ]);
    }
}

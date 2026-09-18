<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesforceContact;
use App\Models\SalesforceSyncLog;
use App\Services\SalesforceContactSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesforceContactAdminController extends Controller
{
    /**
     * Display the Admin panel Salesforce Contacts directory with search and filters.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $leadSource = $request->input('lead_source');
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = SalesforceContact::query()
            ->with(['account', 'owner', 'primeOwner'])
            ->search($search)
            ->filterLeadSource($leadSource);

        $contacts = $query->orderBy('salesforce_updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $totalLocalContacts = SalesforceContact::count();

        $leadSources = SalesforceContact::whereNotNull('lead_source')
            ->where('lead_source', '!=', '')
            ->distinct()
            ->orderBy('lead_source')
            ->pluck('lead_source')
            ->toArray();

        $latestSync = SalesforceSyncLog::where('object_type', 'Contact')
            ->orderByDesc('started_at')
            ->first();

        return view('admin.salesforce-contacts.index', [
            'contacts' => $contacts,
            'totalLocalContacts' => $totalLocalContacts,
            'leadSources' => $leadSources,
            'latestSync' => $latestSync,
            'search' => $search,
            'selectedSource' => $leadSource,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Trigger manual Salesforce Contact synchronization from the Admin Panel.
     */
    public function manualSync(Request $request, SalesforceContactSyncService $syncService): RedirectResponse|JsonResponse
    {
        $forceFull = $request->boolean('full', false);
        $result = $syncService->syncContacts($forceFull, 'manual');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('admin.salesforce_contacts.index')
                ->with('success', $result['message'] ?? 'Salesforce contacts synchronized successfully.');
        }

        return redirect()->route('admin.salesforce_contacts.index')
            ->with('danger', 'Salesforce sync failed: ' . ($result['error'] ?? 'Unknown error.'));
    }

    /**
     * API endpoint to get latest synchronization status and stats for Contacts.
     */
    public function syncStatus(): JsonResponse
    {
        $latestSync = SalesforceSyncLog::where('object_type', 'Contact')
            ->orderByDesc('started_at')
            ->first();
        $totalLocalContacts = SalesforceContact::count();

        return response()->json([
            'latest_sync' => $latestSync,
            'total_local_contacts' => $totalLocalContacts,
        ]);
    }
}

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
        $customOwner = $request->input('custom_owner');
        $ownerId = $request->input('owner_id');
        $primeOwnerId = $request->input('prime_owner_id');
        $sfUserId = $request->input('salesforce_sf_user_id');
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = SalesforceContact::query()
            ->with(['account', 'owner', 'primeOwner', 'salesforceOwner'])
            ->search($search)
            ->filterLeadSource($leadSource)
            ->filterCustomOwner($customOwner)
            ->filterOwner($ownerId)
            ->filterPrimeOwner($primeOwnerId)
            ->filterSalesforceSfUser($sfUserId ? (int)$sfUserId : null);

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

        $customOwners = SalesforceContact::whereNotNull('Custom_Owner__c')
            ->where('Custom_Owner__c', '!=', '')
            ->distinct()
            ->orderBy('Custom_Owner__c')
            ->pluck('Custom_Owner__c')
            ->toArray();

        $sfUsers = \App\Models\SalesforceSfUser::orderBy('name')
            ->select('id', 'salesforce_id', 'name', 'emp_name', 'emp_email')
            ->get();

        $standardUsers = \App\Models\SalesforceUser::orderBy('name')
            ->select('salesforce_id', 'name', 'username')
            ->get();

        $latestSync = SalesforceSyncLog::where('object_type', 'Contact')
            ->orderByDesc('started_at')
            ->first();

        return view('admin.salesforce-contacts.index', [
            'contacts' => $contacts,
            'totalLocalContacts' => $totalLocalContacts,
            'leadSources' => $leadSources,
            'customOwners' => $customOwners,
            'sfUsers' => $sfUsers,
            'standardUsers' => $standardUsers,
            'latestSync' => $latestSync,
            'search' => $search,
            'selectedSource' => $leadSource,
            'selectedCustomOwner' => $customOwner,
            'selectedOwner' => $ownerId,
            'selectedPrimeOwner' => $primeOwnerId,
            'selectedSfUserId' => $sfUserId,
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

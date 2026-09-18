<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesforceLead;
use App\Models\SalesforceSyncLog;
use App\Services\SalesforceLeadSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesforceLeadAdminController extends Controller
{
    /**
     * Display the Admin panel Salesforce Leads directory with search and filters.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $leadSource = $request->input('lead_source');
        $ownerId = $request->input('owner_id');
        $primeOwnerId = $request->input('prime_owner_id');
        $verificationStatus = $request->input('verification_status');
        $sortBy = $request->input('sort_by', 'salesforce_updated_at');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['salesforce_updated_at', 'salesforce_created_at', 'name', 'company', 'status', 'owner_verification_status', 'last_owner_verified_at', 'id'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'salesforce_updated_at';
        }

        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = SalesforceLead::query()
            ->with(['owner', 'primeOwner'])
            ->search($search)
            ->filterStatus($status)
            ->filterLeadSource($leadSource)
            ->filterOwner($ownerId)
            ->filterPrimeOwner($primeOwnerId)
            ->filterOwnerVerificationStatus($verificationStatus);

        $leads = $query->orderBy($sortBy, $sortDir)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $totalLocalLeads = SalesforceLead::count();

        $statuses = SalesforceLead::whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->toArray();

        $leadSources = SalesforceLead::whereNotNull('lead_source')
            ->where('lead_source', '!=', '')
            ->distinct()
            ->orderBy('lead_source')
            ->pluck('lead_source')
            ->toArray();

        $sfUsers = \App\Models\SalesforceSfUser::orderBy('name')
            ->select('salesforce_id', 'name', 'emp_name')
            ->get();

        $standardUsers = \App\Models\SalesforceUser::orderBy('name')
            ->select('salesforce_id', 'name', 'username')
            ->get();

        $latestSync = SalesforceSyncLog::where('object_type', 'Lead')->orWhereNull('object_type')->orderByDesc('started_at')->first();

        return view('admin.salesforce-leads.index', [
            'leads' => $leads,
            'totalLocalLeads' => $totalLocalLeads,
            'statuses' => $statuses,
            'leadSources' => $leadSources,
            'sfUsers' => $sfUsers,
            'standardUsers' => $standardUsers,
            'latestSync' => $latestSync,
            'search' => $search,
            'selectedStatus' => $status,
            'selectedSource' => $leadSource,
            'selectedOwner' => $ownerId,
            'selectedPrimeOwner' => $primeOwnerId,
            'selectedVerificationStatus' => $verificationStatus,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Display or return details for a specific synchronized Salesforce Lead.
     */
    public function show(Request $request, string|int $id): JsonResponse|View
    {
        $lead = SalesforceLead::with(['owner', 'primeOwner', 'campaignMembers.campaign'])
            ->where('id', is_numeric($id) ? (int)$id : -1)
            ->orWhere('salesforce_id', $id)
            ->firstOrFail();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'lead' => $lead,
                'owner' => $lead->owner,
                'prime_owner' => $lead->primeOwner,
                'campaign_members' => $lead->campaignMembers,
            ]);
        }

        return view('admin.salesforce-leads.show', [
            'lead' => $lead,
        ]);
    }

    /**
     * Trigger manual Salesforce Lead synchronization from the Admin Panel.
     */
    public function manualSync(Request $request, SalesforceLeadSyncService $syncService): RedirectResponse|JsonResponse
    {
        $forceFull = $request->boolean('full', false);
        $result = $syncService->syncLeads($forceFull, 'manual');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('admin.salesforce_leads.index')
                ->with('success', $result['message'] ?? 'Salesforce leads synchronized successfully.');
        }

        return redirect()->route('admin.salesforce_leads.index')
            ->with('danger', 'Salesforce sync failed: ' . ($result['error'] ?? 'Unknown error.'));
    }

    /**
     * API endpoint to get latest synchronization status and stats.
     */
    public function syncStatus(): JsonResponse
    {
        $latestSync = SalesforceSyncLog::orderByDesc('started_at')->first();
        $totalLocalLeads = SalesforceLead::count();

        return response()->json([
            'latest_sync' => $latestSync,
            'total_local_leads' => $totalLocalLeads,
        ]);
    }
}

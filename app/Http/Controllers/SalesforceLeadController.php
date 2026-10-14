<?php

namespace App\Http\Controllers;

use App\Services\SalesforceLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesforceLeadController extends Controller
{
    protected SalesforceLeadService $leadService;

    public function __construct(SalesforceLeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    /**
     * Display the Salesforce Leads view page.
     */
    public function index(): View
    {
        return view('salesforce.leads');
    }

    /**
     * API endpoint to fetch paginated Salesforce Leads using JSforce.
     */
    public function apiLeads(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|in:25,50,100',
            'search' => 'sometimes|nullable|string|max:100',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $search = (string) ($validated['search'] ?? '');

        $offset = ($page - 1) * $perPage;

        $user = $request->user() ?: \Illuminate\Support\Facades\Auth::user() ?: \App\Models\User::find(session('user_id'));
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated.',
                'total' => 0,
                'limit' => $perPage,
                'offset' => $offset,
                'page' => $page,
                'totalPages' => 1,
                'records' => [],
            ], 401);
        }

        $ownerService = app(\App\Services\SalesforceOwnerService::class);
        $criteria = $ownerService->resolveOwnerCriteria($user);

        if (!$criteria['valid']) {
            return response()->json([
                'success' => true,
                'total' => 0,
                'limit' => $perPage,
                'offset' => $offset,
                'page' => $page,
                'totalPages' => 1,
                'records' => [],
            ]);
        }

        $ownerName = $criteria['primary_owner_name'] ?? null;
        $primeOwnerId = $criteria['prime_owner_ids'][0] ?? null;

        $result = $this->leadService->getLeads($perPage, $offset, $search, $ownerName, $primeOwnerId);

        return response()->json($result);
    }

    /**
     * Display the Salesforce Connection Test view page.
     */
    public function connectionTestView(): View
    {
        return view('salesforce.connection_test');
    }

    /**
     * API endpoint to test Salesforce connection using JSforce without retrieving records.
     */
    public function apiTestConnection(): JsonResponse
    {
        $result = $this->leadService->testConnection();

        return response()->json($result);
    }
}

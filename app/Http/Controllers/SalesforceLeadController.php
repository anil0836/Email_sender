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

        $result = $this->leadService->getLeads($perPage, $offset, $search);

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

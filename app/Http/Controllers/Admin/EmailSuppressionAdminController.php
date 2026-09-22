<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalSuppression;
use App\Services\EmailSuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailSuppressionAdminController extends Controller
{
    public function __construct(
        protected EmailSuppressionService $suppressionService
    ) {}

    /**
     * Display the email suppressions directory with metrics and filters.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $reason = $request->input('reason');
        $source = $request->input('source');
        $status = $request->input('status', 'suppressed'); // default to active suppressed
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $query = GlobalSuppression::with(['resubscribedByUser', 'createdByUser', 'campaign']);

        if (!empty($search)) {
            $normalizedSearch = strtolower($search);
            $query->where(function ($q) use ($search, $normalizedSearch) {
                $q->where('normalized_email', 'like', "%{$normalizedSearch}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('provider_message_id', 'like', "%{$search}%");
            });
        }

        if (!empty($reason)) {
            $query->where('reason', $reason);
        }

        if (!empty($source)) {
            $query->where('source', $source);
        }

        if (!empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $suppressions = $query->orderBy('updated_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $statistics = $this->suppressionService->getStatistics();

        // Unique reasons and sources for filter dropdowns
        $reasons = GlobalSuppression::select('reason')->distinct()->pluck('reason')->filter();
        $sources = GlobalSuppression::select('source')->distinct()->pluck('source')->filter();

        return view('admin.suppressions.index', compact(
            'suppressions',
            'statistics',
            'search',
            'reason',
            'source',
            'status',
            'perPage',
            'reasons',
            'sources'
        ));
    }

    /**
     * Manually suppress a new email address.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'reason' => 'required|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $userId = Auth::id() ?? session('user_id');

        $this->suppressionService->suppress(
            email: $request->input('email'),
            reason: $request->input('reason'),
            source: 'admin_manual',
            metadata: [
                'note' => $request->input('note'),
                'created_by_user_id' => $userId,
            ],
            userId: $userId
        );

        return redirect()->route('admin.suppressions.index')
            ->with('success', "Email address '{$request->input('email')}' has been globally suppressed.");
    }

    /**
     * Explicitly resubscribe an email address with authorization audit.
     */
    public function resubscribe(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'remark' => 'nullable|string|max:255',
        ]);

        $record = GlobalSuppression::findOrFail($id);
        $userId = Auth::id() ?? session('user_id') ?? 1;
        $remark = $request->input('remark', 'Manual administrative authorization');

        $this->suppressionService->resubscribe($record->normalized_email, $userId, $remark);

        return redirect()->route('admin.suppressions.index')
            ->with('success', "Recipient '{$record->email}' has been successfully resubscribed and sending permission restored.");
    }

    /**
     * Export active suppressions to CSV.
     */
    public function export(Request $request): Response
    {
        $records = GlobalSuppression::where('status', 'suppressed')
            ->orderBy('added_at', 'desc')
            ->get(['email', 'reason', 'source', 'added_at']);

        $csvHeader = "email,reason,source,suppressed_at\n";
        $csvRows = [];

        foreach ($records as $r) {
            $csvRows[] = sprintf(
                '"%s","%s","%s","%s"',
                str_replace('"', '""', $r->email),
                str_replace('"', '""', $r->reason),
                str_replace('"', '""', $r->source),
                $r->added_at ? $r->added_at->toIso8601String() : ''
            );
        }

        $csvData = $csvHeader . implode("\n", $csvRows);

        return response($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="global_suppressions_' . date('Y-m-d') . '.csv"',
        ]);
    }
}

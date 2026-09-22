<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalSuppression;
use App\Services\EmailSuppressionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmailSuppressionImportController extends Controller
{
    public function __construct(
        protected EmailSuppressionService $suppressionService
    ) {}

    /**
     * Show the CSV suppression import form.
     */
    public function showImportForm(): View
    {
        return view('admin.suppressions.import');
    }

    /**
     * Process bulk suppression CSV file.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
            'default_reason' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $defaultReason = trim((string) $request->input('default_reason')) ?: 'Bulk CSV Import';
        $userId = Auth::id() ?? session('user_id');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('danger', 'Unable to open uploaded file.');
        }

        $totalRows = 0;
        $validEmails = 0;
        $invalidEmails = 0;
        $duplicateInFile = 0;
        $alreadySuppressed = 0;
        $newlySuppressed = 0;

        $seenInFile = [];
        $candidates = [];

        // Detect header row
        $header = fgetcsv($handle);
        $emailColIdx = 0;
        $reasonColIdx = -1;

        if ($header !== false) {
            $normalizedHeader = array_map(fn($h) => strtolower(trim((string) $h)), $header);
            $foundEmail = array_search('email', $normalizedHeader, true);
            if ($foundEmail !== false) {
                $emailColIdx = $foundEmail;
            } else {
                // If first row is actually a data row, process it
                if (filter_var($header[0] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $rawEmail = trim($header[0]);
                    $norm = strtolower($rawEmail);
                    $seenInFile[$norm] = true;
                    $candidates[] = [
                        'email' => $rawEmail,
                        'normalized_email' => $norm,
                        'reason' => !empty($header[1]) ? trim($header[1]) : $defaultReason,
                    ];
                    $totalRows++;
                }
            }

            $foundReason = array_search('reason', $normalizedHeader, true);
            if ($foundReason !== false) {
                $reasonColIdx = $foundReason;
            }
        }

        // Read remaining rows
        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            $rawEmail = isset($row[$emailColIdx]) ? trim($row[$emailColIdx]) : '';

            if (empty($rawEmail) || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                $invalidEmails++;
                continue;
            }

            $norm = strtolower($rawEmail);
            if (isset($seenInFile[$norm])) {
                $duplicateInFile++;
                continue;
            }

            $seenInFile[$norm] = true;
            $rowReason = ($reasonColIdx >= 0 && !empty($row[$reasonColIdx])) 
                ? trim($row[$reasonColIdx]) 
                : $defaultReason;

            $candidates[] = [
                'email' => $rawEmail,
                'normalized_email' => $norm,
                'reason' => $rowReason,
            ];
            $validEmails++;
        }
        fclose($handle);

        // Process in chunks of 500
        $chunks = array_chunk($candidates, 500);
        $now = Carbon::now();

        foreach ($chunks as $chunk) {
            $chunkNormEmails = array_column($chunk, 'normalized_email');
            
            // Check which ones already exist as active suppressions
            $existing = GlobalSuppression::whereIn('normalized_email', $chunkNormEmails)
                ->where('status', 'suppressed')
                ->pluck('normalized_email')
                ->toArray();
            $existingMap = array_flip($existing);

            $upsertData = [];
            foreach ($chunk as $item) {
                if (isset($existingMap[$item['normalized_email']])) {
                    $alreadySuppressed++;
                    continue;
                }

                $newlySuppressed++;
                $upsertData[] = [
                    'email' => $item['email'],
                    'normalized_email' => $item['normalized_email'],
                    'reason' => $item['reason'],
                    'source' => 'csv_import',
                    'status' => 'suppressed',
                    'created_by' => $userId,
                    'added_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($upsertData)) {
                GlobalSuppression::upsert(
                    $upsertData,
                    ['normalized_email'],
                    ['email', 'reason', 'source', 'status', 'created_by', 'added_at', 'updated_at']
                );
            }
        }

        $summary = [
            'total_rows' => $totalRows,
            'valid_emails' => $validEmails,
            'invalid_emails' => $invalidEmails,
            'duplicate_in_file' => $duplicateInFile,
            'already_suppressed' => $alreadySuppressed,
            'newly_suppressed' => $newlySuppressed,
        ];

        return redirect()->route('admin.suppressions.import')
            ->with('import_summary', $summary)
            ->with('success', "Suppression CSV import completed: {$newlySuppressed} newly suppressed recipients.");
    }

    /**
     * Download sample CSV template.
     */
    public function downloadSample(): Response
    {
        $csvContent = "email,reason\n"
            . "unsubscribe1@example.com,Customer Unsubscribe Request\n"
            . "spam-complaint@example.com,Spam Complaint\n"
            . "optout@example.com,GDPR Opt-Out Request\n";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="suppression_import_sample.csv"',
        ]);
    }
}

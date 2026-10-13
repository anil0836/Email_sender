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
            'file' => [
                'required',
                'file',
                'max:10240', // Max 10MB
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['csv', 'txt'], true)) {
                        $fail('The uploaded file must have a .csv or .txt extension.');
                    }
                },
            ],
            'default_reason' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $defaultReason = trim((string) $request->input('default_reason')) ?: 'Bulk CSV Import';
        
        $userId = Auth::id() ?? session('user_id');
        if ($userId && !\App\Models\User::where('id', $userId)->exists()) {
            $userId = null;
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('danger', 'Unable to open uploaded file.');
        }

        // Strip UTF-8 BOM if present at the beginning of the file
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
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
            // Strip hidden / BOM chars from column names
            $cleanHeader = array_map(function ($h) {
                $str = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
                return strtolower(trim($str));
            }, $header);

            $isHeader = false;
            foreach ($cleanHeader as $idx => $name) {
                if (in_array($name, ['email', 'email address', 'e-mail', 'mail', 'email_address', 'recipient', 'recipient email', 'recipients'], true)) {
                    $emailColIdx = $idx;
                    $isHeader = true;
                }
                if (in_array($name, ['reason', 'suppression reason', 'reason for suppression', 'notes', 'comment', 'description'], true)) {
                    $reasonColIdx = $idx;
                    $isHeader = true;
                }
            }

            if (!$isHeader) {
                // First row is actually a data row
                $firstVal = trim((string) ($header[$emailColIdx] ?? $header[0] ?? ''));
                if (filter_var($firstVal, FILTER_VALIDATE_EMAIL)) {
                    $norm = strtolower($firstVal);
                    $seenInFile[$norm] = true;
                    $candidates[] = [
                        'email' => $firstVal,
                        'normalized_email' => $norm,
                        'reason' => ($reasonColIdx >= 0 && !empty($header[$reasonColIdx])) ? trim((string) $header[$reasonColIdx]) : $defaultReason,
                    ];
                    $totalRows++;
                    $validEmails++;
                } elseif (!empty($firstVal)) {
                    $totalRows++;
                    $invalidEmails++;
                }
            }
        }

        // Read remaining rows
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $totalRows++;
            $rawEmail = isset($row[$emailColIdx]) ? trim((string) $row[$emailColIdx]) : '';

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
                ? trim((string) $row[$reasonColIdx]) 
                : $defaultReason;

            $candidates[] = [
                'email' => $rawEmail,
                'normalized_email' => $norm,
                'reason' => $rowReason,
            ];
            $validEmails++;
        }
        fclose($handle);

        try {
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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Suppression CSV Import error: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('danger', 'Error processing CSV import: ' . $e->getMessage());
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

<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\RecipientLog;
use App\Models\SendingDomain;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CampaignProcessingService;
use App\Services\SalesforceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function __construct(
        protected SalesforceService $sfService,
        protected AuditService $auditService,
        protected CampaignProcessingService $processingService
    ) {}

    /**
     * Show create campaign view.
     */
    public function createView()
    {
        return view('campaigns.create');
    }

    /**
     * Show campaign detail / audit view.
     */
    public function detailView(string $campaignId)
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return redirect()->route('dashboard_view')->with('danger', 'Campaign not found.');
        }

        return view('campaigns.detail', [
            'campaign_id' => $campaignId,
            'campaignId' => $campaignId,
        ]);
    }

    /**
     * Interactive Pre-Send Validation API.
     */
    public function apiValidate(Request $request)
    {
        $recipientIds = $request->input('recipient_ids', []);
        $recipientEmails = $request->input('recipient_emails', []);
        $user = Auth::user() ?: User::find(session('user_id'));
        $appUsername = $user ? $user->username : 'admin';

        if (empty($recipientIds) && empty($recipientEmails)) {
            return response()->json(['error' => 'No recipients selected or emails pasted.'], 400);
        }

        $approved = [];
        $blockedReasons = [
            'EMAIL_OPT_OUT' => [],
            'GLOBAL_SUPPRESSION' => [],
            'DIFFERENT_OWNER' => [],
            'INVALID_EMAIL' => [],
            'INACTIVE_RECORD' => [],
            'MISSING_CONSENT' => [],
        ];
        $duplicates = [];
        $seenEmails = [];

        // 1. Process explicitly selected IDs
        foreach ($recipientIds as $recId) {
            [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility($recId, $appUsername);

            if (!$record) {
                $blockedReasons['INVALID_EMAIL'][] = [
                    'id' => $recId,
                    'name' => 'Unknown',
                    'email' => 'N/A',
                ];
                continue;
            }

            $recEmail = $record['email'];
            $recName = "{$record['first_name']} {$record['last_name']}";

            if (isset($seenEmails[$recEmail])) {
                $duplicates[] = [
                    'id' => $recId,
                    'name' => $recName,
                    'email' => $recEmail,
                ];
                continue;
            }

            $seenEmails[$recEmail] = true;

            if ($isEligible) {
                $approved[] = [
                    'id' => $recId,
                    'name' => $recName,
                    'email' => $recEmail,
                    'owner_id' => $record['owner_id'],
                ];
            } else {
                $blockedReasons[$reason][] = [
                    'id' => $recId,
                    'name' => $recName,
                    'email' => $recEmail,
                    'owner_id' => $record['owner_id'],
                ];
            }
        }

        // 2. Process pasted raw email list
        foreach ($recipientEmails as $rawEmail) {
            $email = trim($rawEmail);
            if (empty($email)) {
                continue;
            }

            if (isset($seenEmails[$email])) {
                $duplicates[] = [
                    'id' => 'N/A',
                    'name' => 'Duplicate Entry',
                    'email' => $email,
                ];
                continue;
            }

            [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibilityByEmail($email, $appUsername);

            if (!$record) {
                $blockedReasons['INVALID_EMAIL'][] = [
                    'id' => 'N/A',
                    'name' => 'Not in Salesforce CRM',
                    'email' => $email,
                ];
                continue;
            }

            $recEmail = $record['email'];
            $recName = "{$record['first_name']} {$record['last_name']}";
            $seenEmails[$recEmail] = true;

            if ($isEligible) {
                $approved[] = [
                    'id' => $record['id'],
                    'name' => $recName,
                    'email' => $recEmail,
                    'owner_id' => $record['owner_id'],
                ];
            } else {
                $blockedReasons[$reason][] = [
                    'id' => $record['id'],
                    'name' => $recName,
                    'email' => $recEmail,
                    'owner_id' => $record['owner_id'],
                ];
            }
        }

        $totalBlocked = array_sum(array_map('count', $blockedReasons)) + count($duplicates);

        return response()->json([
            'total_selected' => count($recipientIds) + count($recipientEmails),
            'total_approved' => count($approved),
            'total_blocked' => $totalBlocked,
            'approved' => $approved,
            'blocked_by_reason' => $blockedReasons,
            'duplicates' => $duplicates,
        ]);
    }

    /**
     * Submit and Queue / Schedule Outbound Campaign API.
     */
    public function apiSend(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $userId = $user->id;
        $appUsername = $user->username;
        $userRole = $user->role;

        $subject = $request->input('subject');
        $body = $request->input('body');
        $sendingDomain = $request->input('sending_domain');
        $fromAddress = $request->input('from_address');
        $replyTo = session('email') ?: $user->email;
        $recipientIds = $request->input('recipient_ids', []);
        $recipientEmails = $request->input('recipient_emails', []);
        $attachments = $request->input('attachments', []);
        $scheduleTime = $request->input('scheduled_at');

        // 1. Daily limit validation check (rolling 24h)
        $dailyLimit = $user->daily_limit ?? 1000;
        $sentLast24h = DB::table('recipient_logs')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.user_id', $userId)
            ->where('campaigns.created_at', '>=', Carbon::now()->subHours(24))
            ->where('recipient_logs.decision', 'approved')
            ->count();

        $newRecipientsCount = count($recipientIds) + count($recipientEmails);
        if ($sentLast24h + $newRecipientsCount > $dailyLimit) {
            return response()->json([
                'error' => "Daily sending limit exceeded. You have sent/queued {$sentLast24h} emails in the last 24 hours. Your limit is {$dailyLimit}. Sending this campaign with {$newRecipientsCount} recipients would exceed your daily cap.",
            ], 400);
        }

        // 2. Non-admin domain & from_address auto-assignment
        if ($userRole !== 'admin') {
            if ($user->assigned_domain_id) {
                $domainModel = SendingDomain::find($user->assigned_domain_id);
                if ($domainModel) {
                    $sendingDomain = $domainModel->domain_name;
                }
            }
            if (!$sendingDomain) {
                $defaultDomain = SendingDomain::where('is_default', true)->first();
                if ($defaultDomain) {
                    $sendingDomain = $defaultDomain->domain_name;
                }
            }
            $fromAddress = "{$appUsername}@{$sendingDomain}";
        }

        // 3. Schedule time validation
        $scheduledDt = null;
        if ($scheduleTime) {
            try {
                $scheduledDt = Carbon::parse($scheduleTime);
                $now = Carbon::now();

                if ($scheduledDt->lte($now)) {
                    return response()->json(['error' => 'Scheduled time must be in the future.'], 400);
                }

                if ($scheduledDt->gt($now->copy()->addHours(24))) {
                    return response()->json(['error' => 'Scheduled time must be within 24 hours from now.'], 400);
                }

                $scheduleTime = $scheduledDt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Invalid scheduled date/time format.'], 400);
            }
        }

        if (!$subject || !$body || !$sendingDomain || !$fromAddress || !$replyTo) {
            return response()->json(['error' => 'Required fields missing.'], 400);
        }

        if (empty($recipientIds) && empty($recipientEmails)) {
            return response()->json(['error' => 'No recipients selected or emails pasted.'], 400);
        }

        // 4. Confirmation-time recipient validation
        $approvedRecords = [];
        $blockedRecords = [];
        $seenEmails = [];

        foreach ($recipientIds as $recId) {
            [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibility($recId, $appUsername);
            if (!$record) {
                $blockedRecords[] = [$recId, 'INVALID_EMAIL', 'Lead', 'unknown_owner', 'unknown@invalid.com'];
                continue;
            }

            $recEmail = $record['email'];
            $recObj = $record['object_type'];
            $recOwner = $record['owner_id'];

            if (isset($seenEmails[$recEmail])) {
                $blockedRecords[] = [$recId, 'COMPLIANCE_RULE', $recObj, $recOwner, $recEmail];
                continue;
            }
            $seenEmails[$recEmail] = true;

            if ($isEligible) {
                $approvedRecords[] = $record;
            } else {
                $blockedRecords[] = [$recId, $reason, $recObj, $recOwner, $recEmail];
            }
        }

        foreach ($recipientEmails as $rawEmail) {
            $email = trim($rawEmail);
            if (empty($email)) {
                continue;
            }

            if (isset($seenEmails[$email])) {
                $blockedRecords[] = ['N/A', 'COMPLIANCE_RULE', 'Contact', 'unknown_owner', $email];
                continue;
            }

            [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibilityByEmail($email, $appUsername);
            if (!$record) {
                $blockedRecords[] = ['N/A', 'INVALID_EMAIL', 'Contact', 'unknown_owner', $email];
                continue;
            }

            $recEmail = $record['email'];
            $recObj = $record['object_type'];
            $recOwner = $record['owner_id'];
            $seenEmails[$recEmail] = true;

            if ($isEligible) {
                $approvedRecords[] = $record;
            } else {
                $blockedRecords[] = [$record['id'], $reason, $recObj, $recOwner, $recEmail];
            }
        }

        $campaignId = (string) Str::uuid();

        // 5. Determine Campaign Status
        if ($userRole === 'user') {
            $status = 'pending_approval';
        } else {
            $status = $scheduleTime ? 'scheduled' : 'queued';
        }

        $campaign = Campaign::create([
            'id' => $campaignId,
            'subject' => $subject,
            'body' => $body,
            'sending_domain' => $sendingDomain,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'user_id' => $userId,
            'total_requested' => count($recipientIds) + count($recipientEmails),
            'total_approved' => count($approvedRecords),
            'total_blocked' => count($blockedRecords),
            'status' => $status,
            'scheduled_at' => $scheduleTime,
            'attachments' => json_encode($attachments),
        ]);

        // 6. Insert logs for approved recipients
        foreach ($approvedRecords as $rec) {
            RecipientLog::create([
                'campaign_id' => $campaignId,
                'email' => $rec['email'],
                'salesforce_record_id' => $rec['id'],
                'salesforce_object' => $rec['object_type'],
                'record_owner_id' => $rec['owner_id'],
                'decision' => 'approved',
                'delivery_status' => $status,
                'tracking_token' => (string) Str::uuid(),
            ]);
        }

        // 7. Insert logs for blocked recipients
        foreach ($blockedRecords as [$recId, $reason, $recObj, $recOwner, $recEmail]) {
            RecipientLog::create([
                'campaign_id' => $campaignId,
                'email' => $recEmail,
                'salesforce_record_id' => $recId,
                'salesforce_object' => $recObj,
                'record_owner_id' => $recOwner,
                'decision' => 'blocked',
                'decision_reason' => $reason,
                'delivery_status' => 'blocked',
                'tracking_token' => (string) Str::uuid(),
            ]);
        }

        $this->auditService->logActivity(
            $userId,
            'Send Campaign',
            "Created campaign '{$subject}' (ID: {$campaignId}), status: {$status} with " . count($approvedRecords) . ' approved and ' . count($blockedRecords) . ' blocked recipients',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'campaign_id' => $campaignId,
            'message' => "Campaign created with status: {$status}.",
        ]);
    }

    /**
     * Fetch Campaign Detail JSON API.
     */
    public function apiDetail(string $campaignId)
    {
        $campaignRow = DB::table('campaigns')
            ->join('users', 'campaigns.user_id', '=', 'users.id')
            ->leftJoin('users as approvers', 'campaigns.approved_by', '=', 'approvers.id')
            ->where('campaigns.id', $campaignId)
            ->select('campaigns.*', 'users.username as creator_username', 'users.manager_id', 'users.emp_id as creator_emp_id', 'approvers.username as approver_username')
            ->first();

        if (!$campaignRow) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $logs = RecipientLog::where('campaign_id', $campaignId)->get();

        return response()->json([
            'campaign' => (array) $campaignRow,
            'recipient_logs' => $logs,
        ]);
    }

    /**
     * Campaign list for dropdown filter API.
     */
    public function apiDropdown(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $userId = $user->id;
        $role = $user->role;
        $filterUserId = $request->input('user_id') ?: $request->input('team_member_id');

        $query = Campaign::select('id', 'subject')->orderBy('created_at', 'desc');

        if ($role === 'admin') {
            if ($filterUserId) {
                $query->where('user_id', $filterUserId);
            }
        } elseif ($role === 'manager') {
            $subordinateIds = User::where('manager_id', $userId)->pluck('id')->toArray();
            $subordinateIds[] = $userId;

            if ($filterUserId && in_array((int) $filterUserId, $subordinateIds)) {
                $query->where('user_id', $filterUserId);
            } else {
                $query->whereIn('user_id', $subordinateIds);
            }
        } else {
            $query->where('user_id', $userId);
        }

        return response()->json($query->get());
    }

    /**
     * Manager / Admin campaign approval API.
     */
    public function apiApprove(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $userRole = $user->role;
        $userId = $user->id;

        if (!in_array($userRole, ['admin', 'manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $campaignId = $request->input('campaign_id');
        $decision = $request->input('decision'); // 'approve' or 'reject'
        $remark = $request->input('remark', '');

        if (!$campaignId || !in_array($decision, ['approve', 'reject'])) {
            return response()->json(['error' => "campaign_id and decision ('approve' or 'reject') required."], 400);
        }

        $campaign = Campaign::with('user')->find($campaignId);
        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        if ($userRole !== 'admin' && ($campaign->user->manager_id ?? null) !== $userId) {
            return response()->json(['error' => 'Unauthorized: You are not the manager of this user.'], 403);
        }

        $nowStr = Carbon::now();

        if ($decision === 'approve') {
            $newStatus = $campaign->scheduled_at ? 'scheduled' : 'queued';
            $newDeliveryStatus = $campaign->scheduled_at ? 'scheduled' : 'queued';

            $campaign->update([
                'status' => $newStatus,
                'approved_by' => $userId,
                'approval_remark' => $remark,
                'approval_at' => $nowStr,
            ]);

            RecipientLog::where('campaign_id', $campaignId)
                ->where('delivery_status', 'pending_approval')
                ->update(['delivery_status' => $newDeliveryStatus]);

            $message = 'Campaign approved and queued for dispatch.';
        } else {
            $campaign->update([
                'status' => 'rejected',
                'approved_by' => $userId,
                'approval_remark' => $remark,
                'approval_at' => $nowStr,
            ]);

            RecipientLog::where('campaign_id', $campaignId)
                ->where('delivery_status', 'pending_approval')
                ->update(['delivery_status' => 'blocked', 'decision' => 'blocked']);

            $message = 'Campaign rejected.';
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Simulated attachment download endpoint.
     */
    public function downloadAttachment(string $campaignId, string $filename)
    {
        $decodedFilename = urldecode($filename);
        $mockContent = "==================================================\n" .
            "B2B BULK MAIL - CAMPAIGN ATTACHMENT DOWNLOAD\n" .
            "==================================================\n" .
            "Campaign Reference ID: {$campaignId}\n" .
            "Simulated File Name:  {$decodedFilename}\n" .
            "Audit Timestamp:      " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n" .
            "This is a mock-generated attachment download to verify system file extraction\n" .
            "and secure tracking audit protocols.\n" .
            "==================================================";

        return response($mockContent, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$decodedFilename}\"",
        ]);
    }
}

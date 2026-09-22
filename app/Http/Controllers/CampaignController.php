<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\RecipientLog;
use App\Models\SalesforceAccount;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
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
    public function createView(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        if (
            !$user->can('bulk-mail.create') &&
            !$user->can('bulk-mail') &&
            !$user->can('campaign.new') &&
            !$user->can('campaign.create') &&
            !$user->can('/campaign/new')
        ) {
            return redirect()->route('dashboard_view')->with('danger', 'Unauthorized. You do not have permission to create bulk campaigns.');
        }

        $mode = $request->query('mode', 'crm');
        return view('campaigns.create', compact('mode'));
    }

    /**
     * Show bulk email view (paste raw list mode).
     */
    public function bulkEmailView(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->can('bulk-mail.view') && !$user->can('bulk-mail')) {
            return redirect()->route('dashboard_view')->with('danger', 'Unauthorized. You do not have permission to access the bulk email tool.');
        }

        $mode = 'paste';
        return view('campaigns.create', compact('mode'));
    }

    /**
     * Show list of all sent and pending campaigns of the user account.
     */
    public function listView(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        // Fetch campaigns belonging to this user account
        $campaigns = Campaign::where('user_id', $user->id)
            ->with(['approver:id,username,name'])
            ->orderByRaw("CASE WHEN status = 'pending_approval' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return view('campaigns.list', [
            'campaigns' => $campaigns,
            'user' => $user,
        ]);
    }

    /**
     * Show campaign detail / audit view.
     */
    public function detailView(string $campaignId)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $campaign = Campaign::accessibleBy($user)->find($campaignId);
        if (!$campaign) {
            return redirect()->route('dashboard_view')->with('danger', 'Unauthorized or campaign not found.');
        }

        // Fetch accessible campaigns for the interactive sidebar
        $sidebarCampaigns = Campaign::accessibleBy($user)
            ->with('user:id,username,name')
            ->orderByRaw("CASE WHEN status = 'pending_approval' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return view('campaigns.detail', [
            'campaign_id' => $campaignId,
            'campaignId' => $campaignId,
            'sidebarCampaigns' => $sidebarCampaigns,
        ]);
    }

    /**
     * Interactive Pre-Send Validation API.
     */
    public function apiValidate(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (
            !$user->can('bulk-mail.create') &&
            !$user->can('bulk-mail') &&
            !$user->can('campaign.new') &&
            !$user->can('campaign.create') &&
            !$user->can('/campaign/new')
        ) {
            return response()->json(['error' => 'Unauthorized. You do not have permission to compose or validate bulk campaigns.'], 403);
        }

        $recipientIds = $request->input('recipient_ids', []);
        $recipientEmails = $request->input('recipient_emails', []);
        $appUsername = $user->username ?: 'admin';

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
                    'record_type' => 'Unknown',
                    'owner_verification_status' => 'unverified',
                ];
                continue;
            }

            $recEmail = $record['email'];
            $recName = !empty($record['name']) ? $record['name'] : trim("{$record['first_name']} {$record['last_name']}");

            if (isset($seenEmails[$recEmail])) {
                $duplicates[] = [
                    'id' => $recId,
                    'name' => $recName,
                    'email' => $recEmail,
                    'record_type' => $record['object_type'] ?? 'Lead',
                    'owner_verification_status' => $record['owner_verification_status'] ?? 'verified',
                ];
                continue;
            }

            $seenEmails[$recEmail] = true;

            $recordSummary = [
                'id' => $record['id'],
                'local_id' => $record['local_id'] ?? null,
                'name' => $recName,
                'email' => $recEmail,
                'company' => $record['company'] ?? '',
                'record_type' => $record['object_type'] ?? 'Lead',
                'owner_id' => $record['owner_id'] ?? '',
                'salesforce_owner_id' => $record['salesforce_owner_id'] ?? ($record['owner_id'] ?? null),
                'prime_owner_id' => $record['prime_owner_id'] ?? null,
                'owner_name' => $record['owner_name'] ?? '',
                'owner_email' => $record['owner_email'] ?? '',
                'owner_verification_status' => $record['owner_verification_status'] ?? 'verified',
                'last_owner_verified_at' => $record['last_owner_verified_at'] ?? null,
            ];

            if ($isEligible) {
                $approved[] = $recordSummary;
            } else {
                $blockedReasons[$reason][] = $recordSummary;
            }
        }

        // 2. Process pasted raw email list / CSV upload
        foreach ($recipientEmails as $rawEmail) {
            $email = trim($rawEmail);
            if (empty($email)) {
                continue;
            }

            $normalizedEmail = strtolower($email);

            if (isset($seenEmails[$normalizedEmail])) {
                $duplicates[] = [
                    'id' => 'N/A',
                    'name' => 'Duplicate Entry',
                    'email' => $email,
                    'record_type' => 'Contact',
                    'owner_verification_status' => 'verified',
                ];
                continue;
            }

            // Direct Global Suppression Check for CSV / raw entries
            if (\App\Models\GlobalSuppression::isSuppressed($normalizedEmail)) {
                $seenEmails[$normalizedEmail] = true;
                $blockedReasons['GLOBAL_SUPPRESSION'][] = [
                    'id' => 'N/A',
                    'name' => 'Suppressed Recipient (CSV / Pasted)',
                    'email' => $email,
                    'record_type' => 'Contact',
                    'owner_verification_status' => 'verified',
                ];
                continue;
            }

            [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibilityByEmail($email, $appUsername);

            if (!$record) {
                $blockedReasons['INVALID_EMAIL'][] = [
                    'id' => 'N/A',
                    'name' => 'Not in Salesforce CRM',
                    'email' => $email,
                    'record_type' => 'Contact',
                    'owner_verification_status' => 'unverified',
                ];
                continue;
            }

            $recEmail = strtolower(trim($record['email']));
            $recName = !empty($record['name']) ? $record['name'] : trim("{$record['first_name']} {$record['last_name']}");
            $seenEmails[$recEmail] = true;

            $recordSummary = [
                'id' => $record['id'],
                'local_id' => $record['local_id'] ?? null,
                'name' => $recName,
                'email' => $recEmail,
                'company' => $record['company'] ?? '',
                'record_type' => $record['object_type'] ?? 'Contact',
                'owner_id' => $record['owner_id'] ?? '',
                'salesforce_owner_id' => $record['salesforce_owner_id'] ?? ($record['owner_id'] ?? null),
                'prime_owner_id' => $record['prime_owner_id'] ?? null,
                'owner_name' => $record['owner_name'] ?? '',
                'owner_email' => $record['owner_email'] ?? '',
                'owner_verification_status' => $record['owner_verification_status'] ?? 'verified',
                'last_owner_verified_at' => $record['last_owner_verified_at'] ?? null,
            ];

            if ($isEligible) {
                $approved[] = $recordSummary;
            } else {
                $blockedReasons[$reason][] = $recordSummary;
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
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!$user->can('bulk-mail.send') && !$user->can('bulk-mail')) {
            return response()->json(['error' => 'Unauthorized. You do not have permission to send bulk emails.'], 403);
        }

        $userId = $user->id;
        $appUsername = $user->username;
        $userRole = $user->role;

        $subject = $request->input('subject');
        $body = $request->input('body');
        $sendingDomain = $request->input('sending_domain');
        $fromAddress = $request->input('from_address');
        $replyTo = trim((string) $request->input('reply_to'));
        if (empty($replyTo)) {
            $replyTo = config('pabbly.reply_to', 'support@b2bexportsllc.com');
        }
        $recipientIds = $request->input('recipient_ids', []);
        $recipientEmails = $request->input('recipient_emails', []);
        $attachments = $request->input('attachments', []);
        $scheduleTime = $request->input('scheduled_at');
        $templateId = $request->input('template_id');
        $signatureId = $request->input('signature_id');

        $signatureSnapshot = null;
        if ($signatureId) {
            $sig = \App\Models\UserSignature::where('id', $signatureId)->where('user_id', $userId)->first();
            if ($sig) {
                $signatureSnapshot = $sig->renderHtml();
            }
        }

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

        // 2. Sending domain & from_address auto-assignment / fallbacks
        if (!$sendingDomain) {
            if ($user->assigned_domain_id) {
                $domainModel = SendingDomain::find($user->assigned_domain_id);
                if ($domainModel) {
                    $sendingDomain = $domainModel->domain_name;
                }
            }
            if (!$sendingDomain) {
                $defaultDomain = SendingDomain::where('is_default', true)->first();
                $sendingDomain = $defaultDomain ? $defaultDomain->domain_name : 'proitbuyer.com';
            }
        }

        if (!$fromAddress || (!app()->environment('testing') && in_array($fromAddress, ["{$appUsername}@{$sendingDomain}", 'user@proitbuyer.com', 'admin@proitbuyer.com']))) {
            $fromAddress = config('pabbly.from_email', 'rma@proitbuyer.com');
        }

        if (!$replyTo) {
            $replyTo = config('pabbly.reply_to', 'support@b2bexportsllc.com');
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
                $blockedRecords[] = [$recId, 'INVALID_EMAIL', 'Lead', 'unknown_owner', 'unknown@invalid.com', null, null];
                continue;
            }

            $recEmail = $record['email'];
            $recObj = $record['object_type'] ?? 'Lead';
            $recOwner = $record['owner_id'] ?? 'unknown_owner';

            if (isset($seenEmails[$recEmail])) {
                $blockedRecords[] = [$recId, 'COMPLIANCE_RULE', $recObj, $recOwner, $recEmail, $record, null];
                continue;
            }
            $seenEmails[$recEmail] = true;

            if ($isEligible) {
                $approvedRecords[] = $record;
            } else {
                $blockedRecords[] = [$recId, $reason, $recObj, $recOwner, $recEmail, $record, null];
            }
        }

        foreach ($recipientEmails as $rawEmail) {
            $email = trim($rawEmail);
            if (empty($email)) {
                continue;
            }

            $normalizedEmail = strtolower($email);

            if (isset($seenEmails[$normalizedEmail])) {
                $blockedRecords[] = ['N/A', 'COMPLIANCE_RULE', 'Contact', 'unknown_owner', $email, null, null];
                continue;
            }

            if (\App\Models\GlobalSuppression::isSuppressed($normalizedEmail)) {
                $seenEmails[$normalizedEmail] = true;
                $blockedRecords[] = ['N/A', 'GLOBAL_SUPPRESSION', 'Contact', $appUsername, $email, null, null];
                continue;
            }

            [$isEligible, $reason, $record] = $this->sfService->checkRecipientEligibilityByEmail($email, $appUsername);
            if (!$record) {
                $blockedRecords[] = ['N/A', 'INVALID_EMAIL', 'Contact', 'unknown_owner', $email, null, null];
                continue;
            }

            $recEmail = $record['email'];
            $recObj = $record['object_type'] ?? 'Contact';
            $recOwner = $record['owner_id'] ?? 'unknown_owner';
            $seenEmails[$recEmail] = true;

            if ($isEligible) {
                $approvedRecords[] = $record;
            } else {
                $blockedRecords[] = [$record['id'], $reason, $recObj, $recOwner, $recEmail, $record, null];
            }
        }

        $campaignId = (string) Str::uuid();

        // 5. Determine Campaign Status
        // Standard users (role='user') always require manager approval before sending.
        // Admins and Managers bypass approval and go directly to queued/scheduled.
        if ($userRole === 'user') {
            $status = 'pending_approval';
        } else {
            $status = $scheduleTime ? 'scheduled' : 'queued';
        }

        // Resolve Team and Manager via TeamService
        $teamService = app(\App\Services\TeamService::class);
        $team = $teamService->resolveUserTeam($user);
        $managerInfo = $teamService->resolveTeamManager($team);
        $managerSfId = $managerInfo['salesforce_id'] ?? null;
        $managerUserId = $managerInfo['local_user_id'] ?? ($user->manager_id ?? null);

        $campaign = Campaign::create([
            'id' => $campaignId,
            'subject' => $subject,
            'body' => $body,
            'sending_domain' => $sendingDomain,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'user_id' => $userId,
            'template_id' => $templateId,
            'signature_id' => $signatureId,
            'signature_snapshot' => $signatureSnapshot,
            'team' => $team,
            'manager_salesforce_id' => $managerSfId,
            'manager_user_id' => $managerUserId,
            'total_requested' => count($recipientIds) + count($recipientEmails),
            'total_approved' => count($approvedRecords),
            'total_blocked' => count($blockedRecords),
            'status' => $status,
            'scheduled_at' => $scheduleTime,
            'attachments' => json_encode($attachments),
        ]);

        // 6. Insert Campaign Members & logs for approved recipients
        foreach ($approvedRecords as $rec) {
            $member = CampaignMember::create([
                'campaign_id' => $campaignId,
                'record_type' => $rec['object_type'] ?? 'Lead',
                'local_record_id' => $rec['local_id'] ?? null,
                'salesforce_record_id' => $rec['id'],
                'email' => $rec['email'] ?? null,
                'name' => !empty($rec['name']) ? $rec['name'] : trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')),
                'company' => $rec['company'] ?? null,
                'salesforce_owner_id' => $rec['salesforce_owner_id'] ?? ($rec['owner_id'] ?? null),
                'prime_owner_id' => $rec['prime_owner_id'] ?? null,
                'owner_name' => $rec['owner_name'] ?? null,
                'owner_email' => $rec['owner_email'] ?? null,
                'owner_verification_status' => $rec['owner_verification_status'] ?? 'verified',
                'last_owner_verified_at' => !empty($rec['last_owner_verified_at']) ? Carbon::parse($rec['last_owner_verified_at']) : Carbon::now(),
                'status' => $status,
            ]);

            RecipientLog::create([
                'campaign_id' => $campaignId,
                'campaign_member_id' => $member->id,
                'email' => $rec['email'],
                'salesforce_record_id' => $rec['id'],
                'salesforce_object' => $rec['object_type'] ?? 'Lead',
                'record_owner_id' => $rec['owner_id'] ?? 'admin',
                'owner_verification_status' => $member->owner_verification_status,
                'decision' => 'approved',
                'delivery_status' => $status,
                'country' => !empty($rec['country']) ? $rec['country'] : null,
                'region' => !empty($rec['region']) ? $rec['region'] : null,
                'city' => !empty($rec['city']) ? $rec['city'] : null,
                'tracking_token' => (string) Str::uuid(),
            ]);
        }

        // 7. Insert Campaign Members & logs for blocked recipients
        foreach ($blockedRecords as [$recId, $reason, $recObj, $recOwner, $recEmail, $recordData]) {
            $member = CampaignMember::create([
                'campaign_id' => $campaignId,
                'record_type' => $recObj ?: 'Lead',
                'local_record_id' => $recordData['local_id'] ?? null,
                'salesforce_record_id' => $recId,
                'email' => $recEmail,
                'name' => $recordData['name'] ?? 'Blocked Recipient',
                'company' => $recordData['company'] ?? null,
                'salesforce_owner_id' => $recOwner,
                'prime_owner_id' => $recordData['prime_owner_id'] ?? null,
                'owner_name' => $recordData['owner_name'] ?? null,
                'owner_email' => $recordData['owner_email'] ?? null,
                'owner_verification_status' => $recordData['owner_verification_status'] ?? 'unverified',
                'last_owner_verified_at' => Carbon::now(),
                'status' => 'blocked',
            ]);

            RecipientLog::create([
                'campaign_id' => $campaignId,
                'campaign_member_id' => $member->id,
                'email' => $recEmail,
                'salesforce_record_id' => $recId,
                'salesforce_object' => $recObj,
                'record_owner_id' => $recOwner,
                'owner_verification_status' => $member->owner_verification_status,
                'decision' => 'blocked',
                'decision_reason' => $reason,
                'delivery_status' => 'blocked',
                'country' => !empty($recordData['country']) ? $recordData['country'] : null,
                'region' => !empty($recordData['region']) ? $recordData['region'] : null,
                'city' => !empty($recordData['city']) ? $recordData['city'] : null,
                'tracking_token' => (string) Str::uuid(),
            ]);
        }

        $this->auditService->logActivity(
            $userId,
            'Send Campaign',
            "Created campaign '{$subject}' (ID: {$campaignId}), status: {$status} with " . count($approvedRecords) . ' approved and ' . count($blockedRecords) . ' blocked recipients',
            $request->ip()
        );

        // 8. Immediately process queued campaign if not scheduled and not testing
        if ($status === 'queued' && !app()->environment('testing')) {
            try {
                $this->processingService->processQueuedEmails();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("[CampaignController] Immediate processing error: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'campaign_id' => $campaignId,
            'status' => $status,
            'message' => "Campaign created with status: {$status}.",
            'warning' => ($status === 'pending_approval' && !$managerUserId)
                ? 'No manager is currently assigned to your account. The campaign will remain on hold until an admin assigns a manager to your profile.'
                : null,
        ]);
    }

    /**
     * Fetch Campaign Detail JSON API.
     */
    public function apiDetail(string $campaignId)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $campaignExists = Campaign::where('id', $campaignId)->exists();
        if (!$campaignExists) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $accessible = Campaign::accessibleBy($user)->where('id', $campaignId)->exists();
        if (!$accessible) {
            return response()->json(['error' => 'Unauthorized access to campaign.'], 403);
        }

        $campaignRow = DB::table('campaigns')
            ->join('users', 'campaigns.user_id', '=', 'users.id')
            ->leftJoin('users as approvers', 'campaigns.approved_by', '=', 'approvers.id')
            ->leftJoin('salesforce_users as sf_mgr', 'campaigns.manager_salesforce_id', '=', 'sf_mgr.salesforce_id')
            ->leftJoin('users as local_mgr', 'campaigns.manager_user_id', '=', 'local_mgr.id')
            ->where('campaigns.id', $campaignId)
            ->select(
                'campaigns.*',
                'users.username as creator_username',
                'users.manager_id',
                'users.emp_id as creator_emp_id',
                'approvers.username as approver_username',
                'sf_mgr.name as manager_sf_name',
                'sf_mgr.email as manager_sf_email',
                'local_mgr.username as manager_local_username'
            )
            ->first();

        $logs = RecipientLog::where('campaign_id', $campaignId)->get();
        $members = CampaignMember::with(['owner', 'primeOwner'])->where('campaign_id', $campaignId)->get();

        return response()->json([
            'campaign' => (array) $campaignRow,
            'recipient_logs' => $logs,
            'members' => $members,
        ]);
    }

    /**
     * Fetch CRM recipients for Campaign Compose list from real tables (Leads, Contacts, Accounts).
     */
    public function apiRecipients(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $role = $user ? $user->role : 'admin';
        $username = $user ? $user->username : 'admin';

        $type = $request->input('type', 'all');
        $search = $request->input('search');
        $limit = (int) $request->input('limit', 1000);

        $results = [];

        // 1. Leads
        if (in_array($type, ['all', 'Lead', 'lead', 'leads'])) {
            $leadsQuery = SalesforceLead::query()->with(['owner', 'primeOwner']);
            if ($search) {
                $leadsQuery->search($search);
            }
            if ($role !== 'admin') {
                $leadsQuery->where(function ($q) use ($username, $user) {
                    $q->where('owner_id', $username)
                      ->orWhere('owner_email', $user->email ?? $username)
                      ->orWhereHas('owner', function ($oq) use ($username, $user) {
                          $oq->where('username', $username)->orWhere('email', $user->email ?? $username);
                      });
                });
            }

            $leads = $leadsQuery->orderBy('salesforce_updated_at', 'desc')->limit($limit)->get();
            foreach ($leads as $l) {
                $ownerName = $l->owner_name ?: ($l->owner ? $l->owner->name : ($l->primeOwner ? $l->primeOwner->name : ''));
                $results[] = [
                    'id' => $l->salesforce_id,
                    'local_id' => $l->id,
                    'object_type' => 'Lead',
                    'first_name' => $l->first_name ?: '',
                    'last_name' => $l->last_name ?: '',
                    'name' => $l->name ?: trim($l->first_name . ' ' . $l->last_name) ?: 'Unnamed Lead',
                    'email' => $l->email ?: '',
                    'company' => $l->company ?: '',
                    'phone' => $l->phone ?: $l->mobile_phone,
                    'owner_id' => $l->owner_id ?: ($l->prime_owner_id ?: ''),
                    'salesforce_owner_id' => $l->owner_id,
                    'prime_owner_id' => $l->prime_owner_id,
                    'owner_name' => $ownerName,
                    'owner_email' => $l->owner_email ?: ($l->owner ? $l->owner->email : ''),
                    'owner_verification_status' => $l->owner_verification_status ?: 'verified',
                    'last_owner_verified_at' => $l->last_owner_verified_at ? $l->last_owner_verified_at->toIso8601String() : null,
                    'status' => $l->status ?: 'New',
                    'opted_out' => 0,
                    'consent_status' => 'valid',
                    'deal_category' => $l->industry ?: 'General',
                    'region' => $l->state ?: 'Global',
                    'country' => $l->country ?: '',
                ];
            }
        }

        // 2. Contacts
        if (in_array($type, ['all', 'Contact', 'contact', 'contacts'])) {
            $contactsQuery = SalesforceContact::query()->with(['owner', 'primeOwner', 'account']);
            if ($search) {
                $contactsQuery->search($search);
            }
            if ($role !== 'admin') {
                $contactsQuery->where(function ($q) use ($username, $user) {
                    $q->where('owner_id', $username)
                      ->orWhere('owner_email', $user->email ?? $username)
                      ->orWhereHas('owner', function ($oq) use ($username, $user) {
                          $oq->where('username', $username)->orWhere('email', $user->email ?? $username);
                      });
                });
            }

            $contacts = $contactsQuery->orderBy('salesforce_updated_at', 'desc')->limit($limit)->get();
            foreach ($contacts as $c) {
                $ownerName = $c->owner_name ?: ($c->owner ? $c->owner->name : ($c->primeOwner ? $c->primeOwner->name : ''));
                $company = $c->account ? $c->account->name : '';
                $results[] = [
                    'id' => $c->salesforce_id,
                    'local_id' => $c->id,
                    'object_type' => 'Contact',
                    'first_name' => $c->first_name ?: '',
                    'last_name' => $c->last_name ?: '',
                    'name' => $c->name ?: trim($c->first_name . ' ' . $c->last_name) ?: 'Unnamed Contact',
                    'email' => $c->email ?: '',
                    'company' => $company,
                    'phone' => $c->phone ?: $c->mobile_phone,
                    'owner_id' => $c->owner_id ?: ($c->prime_owner_id ?: ''),
                    'salesforce_owner_id' => $c->owner_id,
                    'prime_owner_id' => $c->prime_owner_id,
                    'owner_name' => $ownerName,
                    'owner_email' => $c->owner_email ?: ($c->owner ? $c->owner->email : ''),
                    'owner_verification_status' => $c->owner_verification_status ?: 'verified',
                    'last_owner_verified_at' => $c->last_owner_verified_at ? $c->last_owner_verified_at->toIso8601String() : null,
                    'status' => 'Active',
                    'opted_out' => 0,
                    'consent_status' => 'valid',
                    'deal_category' => $c->department ?: 'General',
                    'region' => $c->mailing_state ?: 'Global',
                    'country' => $c->mailing_country ?: '',
                ];
            }
        }

        // 3. Accounts
        if (in_array($type, ['all', 'Account', 'account', 'accounts'])) {
            $accountsQuery = SalesforceAccount::query()->with(['owner', 'primeOwner']);
            if ($search) {
                $accountsQuery->search($search);
            }
            if ($role !== 'admin') {
                $accountsQuery->where(function ($q) use ($username, $user) {
                    $q->where('owner_id', $username)
                      ->orWhere('owner_email', $user->email ?? $username)
                      ->orWhereHas('owner', function ($oq) use ($username, $user) {
                          $oq->where('username', $username)->orWhere('email', $user->email ?? $username);
                      });
                });
            }

            $accounts = $accountsQuery->orderBy('salesforce_updated_at', 'desc')->limit($limit)->get();
            foreach ($accounts as $a) {
                $ownerName = $a->owner_name ?: ($a->owner ? $a->owner->name : ($a->primeOwner ? $a->primeOwner->name : ''));
                $results[] = [
                    'id' => $a->salesforce_id,
                    'local_id' => $a->id,
                    'object_type' => 'Account',
                    'first_name' => '',
                    'last_name' => '',
                    'name' => $a->name ?: 'Unnamed Account',
                    'email' => '',
                    'company' => $a->name ?: '',
                    'phone' => $a->phone ?: '',
                    'owner_id' => $a->owner_id ?: ($a->prime_owner_id ?: ''),
                    'salesforce_owner_id' => $a->owner_id,
                    'prime_owner_id' => $a->prime_owner_id,
                    'owner_name' => $ownerName,
                    'owner_email' => $a->owner_email ?: ($a->owner ? $a->owner->email : ''),
                    'owner_verification_status' => $a->owner_verification_status ?: 'verified',
                    'last_owner_verified_at' => $a->last_owner_verified_at ? $a->last_owner_verified_at->toIso8601String() : null,
                    'status' => $a->type ?: 'Active',
                    'opted_out' => 0,
                    'consent_status' => 'valid',
                    'deal_category' => $a->industry ?: 'General',
                    'region' => $a->billing_state ?: 'Global',
                    'country' => $a->billing_country ?: '',
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * Campaign list for dropdown filter API.
     */
    public function apiDropdown(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json([], 401);
        }

        $filterUserId = $request->input('user_id') ?: $request->input('team_member_id');
        $filterTeam = $request->input('team');

        $query = Campaign::accessibleBy($user)
            ->with('user:id,username,name')
            ->select('id', 'subject', 'team', 'status', 'user_id', 'total_requested', 'total_approved', 'approved_by', 'created_at', 'scheduled_at')
            ->orderByRaw("CASE WHEN status = 'pending_approval' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc');

        if ($filterUserId) {
            $query->where('user_id', $filterUserId);
        }
        if ($filterTeam) {
            $query->where('team', $filterTeam);
        }

        return response()->json($query->get());
    }

    /**
     * Manager / Admin campaign approval API.
     */
    public function apiApprove(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userRole = $user->role;
        $userId = $user->id;

        $teamService = app(\App\Services\TeamService::class);
        $isManager = $userRole === 'admin' || $userRole === 'manager' || $teamService->isTeamManager($user);

        if (!$isManager) {
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

        // Idempotency guard: only pending_approval campaigns can be approved or rejected.
        if ($campaign->status !== 'pending_approval') {
            return response()->json([
                'error' => "This campaign cannot be actioned. Current status is '{$campaign->status}'. Only campaigns in 'pending_approval' status can be approved or rejected.",
            ], 409);
        }

        // Authorization check: Admin OR manager of this user/campaign
        $isAuthorized = false;
        if ($userRole === 'admin') {
            $isAuthorized = true;
        } elseif ($campaign->manager_user_id === $userId) {
            $isAuthorized = true;
        } elseif ($campaign->user && $teamService->isManagerOfUser($user, $campaign->user)) {
            $isAuthorized = true;
        } elseif ($campaign->team && in_array($campaign->team, $teamService->getManagedTeamsForUser($user))) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return response()->json(['error' => 'Unauthorized: You are not the manager of this campaign or team.'], 403);
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

            if ($newStatus === 'queued' && !app()->environment('testing')) {
                try {
                    $this->processingService->processQueuedEmails();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("[CampaignController] Approval processing error: " . $e->getMessage());
                }
            }

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

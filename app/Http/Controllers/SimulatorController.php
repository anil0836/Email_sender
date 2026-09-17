<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\InboundReply;
use App\Models\RecipientLog;
use App\Models\SalesforceMockRecord;
use App\Models\User;
use App\Services\SalesforceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SimulatorController extends Controller
{
    public function __construct(
        protected SalesforceService $sfService
    ) {}

    /**
     * Show testing simulator view.
     */
    public function index()
    {
        return view('simulator.index');
    }

    /**
     * Fetch CRM recipients for Campaign Compose list.
     */
    public function apiSalesforceRecipients(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $role = $user ? $user->role : 'admin';
        $username = $user ? $user->username : 'admin';

        if ($role === 'admin') {
            $records = SalesforceMockRecord::all();
        } else {
            $records = SalesforceMockRecord::where('owner_id', $username)->get();
        }

        return response()->json($records);
    }

    /**
     * Mock Salesforce records directory CRUD for simulator.
     */
    public function apiSalesforceRecords(Request $request)
    {
        if ($request->isMethod('post')) {
            $objType = $request->input('object_type', 'Contact');
            $prefix = ($objType === 'Contact') ? '003SF' : '00QSF';
            $sfId = $prefix . strtoupper(Str::random(10));

            $record = SalesforceMockRecord::create([
                'id' => $sfId,
                'object_type' => $objType,
                'first_name' => $request->input('first_name', 'Test'),
                'last_name' => $request->input('last_name', 'Record'),
                'email' => $request->input('email'),
                'owner_id' => $request->input('owner_id', 'admin'),
                'opted_out' => (bool) $request->input('opted_out', false),
                'status' => $request->input('status', 'Active'),
                'consent_status' => $request->input('consent_status', 'valid'),
                'lawful_basis' => $request->input('lawful_basis', 'Consent'),
                'deal_category' => $request->input('deal_category'),
                'region' => $request->input('region'),
                'country' => $request->input('country'),
            ]);

            return response()->json(['success' => true, 'id' => $sfId]);
        }

        return response()->json(SalesforceMockRecord::all());
    }

    /**
     * Update Salesforce Mock Record (triggers CDC cache invalidation).
     */
    public function apiUpdateSalesforce(Request $request)
    {
        $recordId = $request->input('id');
        $updates = $request->input('updates', []);

        if (!$recordId || empty($updates)) {
            return response()->json(['error' => 'Record ID and updates payload required'], 400);
        }

        $this->sfService->updateSalesforceRecord($recordId, $updates);

        return response()->json([
            'success' => true,
            'message' => "Salesforce record {$recordId} updated. CDC cache invalidation triggered.",
        ]);
    }

    /**
     * Simulate Provider Webhook (Delivered, Bounced, Spam Complaint).
     */
    public function apiTriggerWebhook(Request $request)
    {
        $providerMsgId = $request->input('provider_message_id');
        $eventType = $request->input('event'); // delivered, bounce, spam_complaint
        $errorMsg = $request->input('error_message');

        if (!$providerMsgId || !$eventType) {
            return response()->json(['error' => 'Provider Message ID and Event required'], 400);
        }

        $log = RecipientLog::where('provider_message_id', $providerMsgId)->first();
        if (!$log) {
            return response()->json(['error' => 'Message ID not found'], 404);
        }

        $log->update([
            'delivery_status' => $eventType,
            'error_message' => $errorMsg,
        ]);

        return response()->json(['success' => true, 'message' => "Delivery status updated to {$eventType}."]);
    }

    /**
     * Simulate Inbound Email Reply.
     */
    public function apiInboundReply(Request $request)
    {
        $campaignId = $request->input('campaign_id');
        $senderEmail = $request->input('email');
        $subject = $request->input('subject', 'Reply');
        $body = $request->input('body', '');

        if (!$campaignId || !$senderEmail) {
            return response()->json(['error' => 'Campaign ID and sender email required'], 400);
        }

        $log = RecipientLog::where('campaign_id', $campaignId)->where('email', $senderEmail)->first();

        $mappedSfId = $log ? $log->salesforce_record_id : null;
        $mappedOwnerId = $log ? $log->record_owner_id : null;

        if (!$mappedSfId) {
            $sfRecord = SalesforceMockRecord::where('email', $senderEmail)->first();
            if ($sfRecord) {
                $mappedSfId = $sfRecord->id;
                $mappedOwnerId = $sfRecord->owner_id;
            } else {
                $mappedOwnerId = 'admin';
            }
        }

        InboundReply::create([
            'campaign_id' => $campaignId,
            'recipient_email' => $senderEmail,
            'reply_subject' => $subject,
            'reply_body' => $body,
            'received_at' => Carbon::now(),
            'mapped_salesforce_record_id' => $mappedSfId,
            'mapped_owner_id' => $mappedOwnerId,
        ]);

        return response()->json(['success' => true, 'message' => 'Inbound reply registered successfully.']);
    }

    /**
     * Clear Cache Helper.
     */
    public function apiClearCache()
    {
        $this->sfService->clearAllCache();
        return response()->json(['success' => true, 'message' => 'Salesforce client cache cleared.']);
    }
}

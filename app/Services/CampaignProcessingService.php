<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\RecipientLog;
use App\Models\SendingDomain;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignProcessingService
{
    public function __construct(
        protected SalesforceService $sfService
    ) {}

    /**
     * Processes all queued and scheduled campaigns.
     * Returns the count of processed emails.
     */
    public function processQueuedEmails(): int
    {
        $now = Carbon::now();
        $processedCount = 0;

        // Find campaigns in 'queued', 'sending', or 'scheduled' (whose time has arrived)
        $campaigns = Campaign::with('user')
            ->whereIn('status', ['queued', 'sending'])
            ->orWhere(function ($query) use ($now) {
                $query->where('status', 'scheduled')
                      ->where('scheduled_at', '<=', $now);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($campaigns as $campaign) {
            $campaignId = $campaign->id;
            $appUsername = $campaign->user ? $campaign->user->username : 'admin';
            $sendingDomain = $campaign->sending_domain;

            // Transition status to 'sending' if 'queued' or 'scheduled'
            if (in_array($campaign->status, ['queued', 'scheduled'])) {
                $campaign->update(['status' => 'sending']);
                RecipientLog::where('campaign_id', $campaignId)
                    ->where('delivery_status', 'scheduled')
                    ->update(['delivery_status' => 'queued']);
            }

            // Find all queued approved recipients
            $recipients = RecipientLog::where('campaign_id', $campaignId)
                ->where('delivery_status', 'queued')
                ->where('decision', 'approved')
                ->get();

            if ($recipients->isEmpty()) {
                // No more queued recipients, mark campaign as completed
                $campaign->update(['status' => 'completed']);
                continue;
            }

            // Fetch Server configuration: check user assigned server first, then domain server
            $serverConfig = null;
            if ($campaign->user && $campaign->user->assigned_server_id) {
                $serverConfig = Server::where('id', $campaign->user->assigned_server_id)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$serverConfig) {
                $domainModel = SendingDomain::where('domain_name', $sendingDomain)
                    ->where('status', 'enabled')
                    ->first();
                if ($domainModel && $domainModel->server_id) {
                    $serverConfig = Server::where('id', $domainModel->server_id)
                        ->where('is_active', true)
                        ->first();
                }
            }

            if (!$serverConfig) {
                $errorMessage = "No active server configuration or domain enabled for {$sendingDomain}";
                Log::warning("[CampaignProcessing] Campaign {$campaignId} error: {$errorMessage}");

                RecipientLog::where('campaign_id', $campaignId)
                    ->where('delivery_status', 'queued')
                    ->update([
                        'delivery_status' => 'failed',
                        'error_message' => $errorMessage,
                    ]);

                $campaign->update(['status' => 'completed']);
                continue;
            }

            foreach ($recipients as $recipient) {
                $recLogId = $recipient->id;
                $recordId = $recipient->salesforce_record_id;
                $email = $recipient->email;

                // MANDATORY FINAL SEND-TIME REVALIDATION AGAINST SALESFORCE CRM
                [$isEligible, $reason, $sfRecord] = $this->sfService->checkRecipientEligibility($recordId, $appUsername);

                if (!$isEligible) {
                    // Eligibility changed since selection! Block sending
                    Log::info("[CampaignProcessing] Recipient {$email} blocked at send-time: {$reason}");

                    $recipient->update([
                        'decision' => 'blocked',
                        'decision_reason' => $reason,
                        'delivery_status' => 'blocked',
                        'validated_at' => Carbon::now(),
                    ]);

                    // Update campaign totals
                    $campaign->decrement('total_approved');
                    $campaign->increment('total_blocked');
                    continue;
                }

                // Process HTML Body: merge fields substitution, tracking pixel, and unsubscribe footer
                $body = $campaign->body;
                $firstName = $sfRecord['first_name'] ?? 'Valued';
                $lastName = $sfRecord['last_name'] ?? 'Customer';

                $body = str_replace(['{{FirstName}}', '{{ FirstName }}'], $firstName, $body);
                $body = str_replace(['{{LastName}}', '{{ LastName }}'], $lastName, $body);

                $trackingToken = $recipient->tracking_token;
                $pixelUrl = url("/track/open/{$trackingToken}");
                $unsubUrl = url("/track/unsubscribe/{$trackingToken}");

                $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;" />';
                $unsubFooter = '<p style="font-size: 11px; color: #64748b; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px;">This email was sent to ' . e($email) . '. If you wish to unsubscribe, please <a href="' . $unsubUrl . '">click here</a>.</p>';

                $finalBody = $body . $unsubFooter . $pixelTag;

                $providerMsgId = 'msg-' . Str::random(16);
                $sentAt = Carbon::now();

                $recipient->update([
                    'delivery_status' => 'sent',
                    'provider_message_id' => $providerMsgId,
                    'sent_at' => $sentAt,
                    'validated_at' => $sentAt,
                ]);

                $processedCount++;
            }

            // Check if all queued recipients for this campaign have finished
            $remaining = RecipientLog::where('campaign_id', $campaignId)
                ->where('delivery_status', 'queued')
                ->where('decision', 'approved')
                ->count();

            if ($remaining === 0) {
                $campaign->update(['status' => 'completed']);
            }
        }

        return $processedCount;
    }
}

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
use Throwable;

class CampaignProcessingService
{
    public function __construct(
        protected SalesforceService $sfService,
        protected PabblyService $pabblyService,
        protected CampaignMergeFieldService $mergeFieldService
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

            $fromEmail = $campaign->from_address ?: config('pabbly.from_email', 'rma@proitbuyer.com');
            $fromName = config('pabbly.from_name', 'anil patel');
            $deliveryServerId = config('pabbly.delivery_server_id', 'send-with-us');
            $pabblyApiKey = config('pabbly.api_key');

            foreach ($recipients as $recipient) {
                $recLogId = $recipient->id;
                $recordId = $recipient->salesforce_record_id;
                $email = $recipient->email;

                // MANDATORY DIRECT SUPPRESSION CHECK (RACE-CONDITION SHIELD)
                if (app(EmailSuppressionService::class)->isSuppressed($email)) {
                    Log::warning("[CampaignProcessing] Immediate send-time suppression caught: {$email} is suppressed.");

                    $recipient->update([
                        'decision' => 'blocked',
                        'decision_reason' => 'GLOBAL_SUPPRESSION',
                        'delivery_status' => 'blocked',
                        'validated_at' => Carbon::now(),
                    ]);

                    if ($recipient->campaign_member_id) {
                        \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                            'status' => 'blocked',
                        ]);
                    }

                    $campaign->decrement('total_approved');
                    $campaign->increment('total_blocked');
                    continue;
                }

                // MANDATORY FINAL SEND-TIME REVALIDATION AGAINST SALESFORCE CRM
                if ($recordId && $recordId !== 'N/A' && !str_starts_with($recordId, '003SF0000000_')) {
                    [$isEligible, $reason, $sfRecord] = $this->sfService->checkRecipientEligibility($recordId, $appUsername);
                } else {
                    [$isEligible, $reason, $sfRecord] = $this->sfService->checkRecipientEligibilityByEmail($email, $appUsername);
                }

                if (!$isEligible) {
                    // Eligibility changed since selection! Block sending
                    Log::info("[CampaignProcessing] Recipient {$email} blocked at send-time: {$reason}");

                    $recipient->update([
                        'decision' => 'blocked',
                        'decision_reason' => $reason,
                        'delivery_status' => 'blocked',
                        'validated_at' => Carbon::now(),
                    ]);

                    if ($recipient->campaign_member_id) {
                        \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                            'status' => 'blocked',
                        ]);
                    }

                    // Update campaign totals
                    $campaign->decrement('total_approved');
                    $campaign->increment('total_blocked');
                    continue;
                }

                // Process Subject & HTML Body: per-recipient dynamic merge fields substitution
                $personalizedSubject = $this->mergeFieldService->resolve(
                    $campaign->subject,
                    $sfRecord,
                    $campaign->user,
                    null,
                    false
                );

                $personalizedBody = $this->mergeFieldService->resolve(
                    $campaign->body,
                    $sfRecord,
                    $campaign->user,
                    $campaign->signature_snapshot,
                    true
                );

                $recipientName = $sfRecord['name'] ?? trim(($sfRecord['first_name'] ?? '') . ' ' . ($sfRecord['last_name'] ?? ''));
                if (empty($recipientName)) {
                    $recipientName = $sfRecord['email'] ?? 'Valued Customer';
                }

                $trackingToken = $recipient->tracking_token;

$baseUrl = rtrim(config('app.url'), '/');

$pixelUrl = "{$baseUrl}/track/open/{$trackingToken}";
$unsubUrl = "{$baseUrl}/track/unsubscribe/{$trackingToken}";

                $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;" />';
                $unsubFooter = '<p style="font-size: 11px; color: #64748b; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px;">This email was sent to ' . e($email) . '. If you wish to unsubscribe, please <a href="' . $unsubUrl . '">click here</a>.</p>';

                $finalBody = $personalizedBody . $unsubFooter . $pixelTag;

                $providerMsgId = 'msg-' . Str::random(16);
                $sentAt = Carbon::now();

                // Send via Pabbly if configured, or simulate if testing
                if (!empty($pabblyApiKey) && !app()->environment('testing')) {
                    try {
                        $replyToEmail = $campaign->reply_to ?: config('pabbly.reply_to', 'support@b2bexportsllc.com');
                        $pabblyResult = $this->pabblyService->sendEmail(
                            $email,
                            $recipientName,
                            $personalizedSubject,
                            $finalBody,
                            $fromEmail,
                            $fromName,
                            $deliveryServerId,
                            $replyToEmail
                        );

                        $providerMsgId = $pabblyResult['pabbly_campaign_id'] ?? $providerMsgId;

                        $recipient->update([
                            'delivery_status' => 'sent',
                            'provider_message_id' => $providerMsgId,
                            'sent_at' => $sentAt,
                            'validated_at' => $sentAt,
                        ]);

                        if ($recipient->campaign_member_id) {
                            \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                                'status' => 'sent',
                            ]);
                        }

                        $processedCount++;
                    } catch (Throwable $e) {
                        Log::error("[CampaignProcessing] Failed to send email to {$email} via Pabbly: " . $e->getMessage());

                        $recipient->update([
                            'delivery_status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'validated_at' => $sentAt,
                        ]);

                        if ($recipient->campaign_member_id) {
                            \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                                'status' => 'failed',
                            ]);
                        }
                    }
                } else {
                    // Simulated dispatch (for testing environment)
                    $recipient->update([
                        'delivery_status' => 'sent',
                        'provider_message_id' => $providerMsgId,
                        'sent_at' => $sentAt,
                        'validated_at' => $sentAt,
                    ]);

                    if ($recipient->campaign_member_id) {
                        \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                            'status' => 'sent',
                        ]);
                    }

                    $processedCount++;
                }
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

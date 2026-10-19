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
            ->where(function ($query) use ($now) {
                $query->whereIn('status', ['queued', 'sending'])
                      ->orWhere(function ($q) use ($now) {
                          $q->where('status', 'scheduled')
                            ->where('scheduled_at', '<=', $now);
                      });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($campaigns as $campaign) {
            // STRICT SEND-TIME APPROVAL SHIELD: Must be fully approved to send emails
            if (!$campaign->isFullyApproved()) {
                Log::warning("[CampaignProcessing] Campaign {$campaign->id} is not fully approved. Status: {$campaign->status}. Skipping sending.");
                continue;
            }

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

            $fromEmail = $campaign->from_address ?: config('pabbly.from_email', 'contact@mailer-b2bexportsllc.com');
            if ($fromEmail === 'rma@proitbuyer.com' || str_ends_with($fromEmail, '@proitbuyer.com')) {
                $fromEmail = config('pabbly.from_email', 'contact@mailer-b2bexportsllc.com');
            }
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
                $isRawRecipient = str_starts_with($recordId ?? '', 'RAW_') || $recordId === 'N/A' || ($recipient->salesforce_object ?? '') === 'Raw';

                if ($isRawRecipient) {
                    // Bulk raw list emails do not verify against Salesforce CRM data
                    $isEligible = true;
                    $reason = null;
                    $sfRecord = [
                        'id' => $recordId,
                        'name' => $email,
                        'email' => $email,
                        'first_name' => '',
                        'last_name' => '',
                        'company' => '',
                        'object_type' => 'Contact',
                    ];
                } elseif ($recordId && !str_starts_with($recordId, '003SF0000000_')) {
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

                // Build attachment download block if campaign has attached files
                $attachmentHtml = '';
                if (!empty($campaign->attachments)) {
                    $attachmentList = is_string($campaign->attachments) ? json_decode($campaign->attachments, true) : $campaign->attachments;
                    if (is_array($attachmentList) && !empty($attachmentList)) {
                        $attachmentLinks = [];
                        $attachmentService = app(\App\Services\AttachmentService::class);
                        foreach ($attachmentList as $filename) {
                            $attachment = $attachmentService->getOrCreateForCampaignFile($campaign, $filename, $campaign->user_id);
                            $downloadUrl = $attachment->getDownloadUrl();
                            $safeFilename = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
                            $fileSizeFormatted = $attachment->file_size > 0 ? ' (' . $attachment->formatted_file_size . ')' : '';

                            $attachmentLinks[] = '<li style="margin: 8px 0; padding: 12px 16px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; list-style: none;">'
                                . '<div style="font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">'
                                . 'File: <strong>' . $safeFilename . '</strong>' . $fileSizeFormatted
                                . '</div>'
                                
. '<div style="font-size: 12px; color: #475569;">'
. 'Download link: <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" style="color: #2563eb; text-decoration: underline; font-weight: 600;" target="_blank" rel="noopener noreferrer">Click Here to Download</a>'
. '</div>'

                                . '</li>';
                        }
                        $attachmentHtml = '<div style="margin-top: 24px; padding: 16px 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">'
                            . '<p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #1e293b;">📎 Download Your Attachment (' . count($attachmentList) . '):</p>'
                            . '<ul style="margin: 0; padding: 0;">' . implode('', $attachmentLinks) . '</ul>'
                            . '<p style="margin: 10px 0 0 0; font-size: 11px; color: #64748b;">Direct download link &bull; No login or account required.</p>'
                            . '</div>';
                    }
                }

                $finalBody = $personalizedBody . $attachmentHtml . $unsubFooter . $pixelTag;

                $providerMsgId = 'msg-' . Str::random(16);
                $sentAt = Carbon::now();

                // Send via Pabbly if configured, or simulate if testing
                if (!empty($pabblyApiKey) && !app()->environment('testing')) {
                    try {
                        $replyToEmail = trim((string) ($campaign->reply_to ?: ($campaign->user?->email ?: config('pabbly.reply_to', 'support@b2bexportsllc.com'))));
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

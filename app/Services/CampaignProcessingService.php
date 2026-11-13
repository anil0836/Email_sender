<?php

namespace App\Services;

use App\Exceptions\PabblyApiException;
use App\Exceptions\PabblyRateLimitException;
use App\Models\Campaign;
use App\Models\RecipientLog;
use App\Models\SendingDomain;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CampaignProcessingService
{
    protected ?PabblyRateLimiterService $rateLimiter = null;
    protected static ?bool $hasRetryColumns = null;

    public function __construct(
        protected SalesforceService $sfService,
        protected PabblyService $pabblyService,
        protected CampaignMergeFieldService $mergeFieldService,
        ?EmailRendererService $emailRenderer = null,
        ?PabblyRateLimiterService $rateLimiter = null
    ) {
        $this->emailRenderer = $emailRenderer ?: app(EmailRendererService::class);
        $this->rateLimiter = $rateLimiter ?: app(PabblyRateLimiterService::class);
    }

    /**
     * Defensive check whether the retry_count and next_retry_at columns exist on recipient_logs.
     */
    protected function hasRetryColumns(): bool
    {
        if (static::$hasRetryColumns === null) {
            try {
                static::$hasRetryColumns = Schema::hasColumn('recipient_logs', 'next_retry_at');
            } catch (Throwable) {
                static::$hasRetryColumns = false;
            }
        }
        return static::$hasRetryColumns;
    }

    /**
     * Processes queued and scheduled campaigns.
     * Enforces rate limiting, concurrency protection, and intelligent retries.
     *
     * @param int|null $maxEmails Maximum emails to process in this run
     * @param int|null $maxExecutionSeconds Maximum run time in seconds
     * @return int Count of dispatched emails
     */
    public function processQueuedEmails(?int $maxEmails = null, ?int $maxExecutionSeconds = null): int
    {
        $startTime = microtime(true);
        $now = Carbon::now();
        $processedCount = 0;

        $pabblyApiKey = (string) config('pabbly.api_key', '');

        // Global Rate-Limit Cooldown Check: avoid hammering provider if active cooldown
        if ($this->rateLimiter->isInCooldown($pabblyApiKey)) {
            $rem = $this->rateLimiter->getCooldownRemainingSeconds($pabblyApiKey);
            Log::info("[CampaignProcessing] Global Pabbly rate-limit cooldown active ({$rem}s remaining). Skipping queue run.");
            return 0;
        }

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
            // Execution limit checks
            if ($maxEmails !== null && $processedCount >= $maxEmails) {
                break;
            }
            if ($maxExecutionSeconds !== null && (microtime(true) - $startTime) >= $maxExecutionSeconds) {
                break;
            }

            // Check if Pabbly entered cooldown during loop
            if ($this->rateLimiter->isInCooldown($pabblyApiKey)) {
                break;
            }

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

            // Find all queued approved recipients eligible for dispatch (not in retry delay)
            $recipientsQuery = RecipientLog::where('campaign_id', $campaignId)
                ->where('delivery_status', 'queued')
                ->where('decision', 'approved');

            if ($this->hasRetryColumns()) {
                $recipientsQuery->where(function ($q) use ($now) {
                    $q->whereNull('next_retry_at')
                      ->orWhere('next_retry_at', '<=', $now);
                });
            }

            $recipients = $recipientsQuery->orderBy('created_at', 'asc')->get();

            if ($recipients->isEmpty()) {
                $waitingRetry = false;
                if ($this->hasRetryColumns()) {
                    // Check if any recipients are still waiting for retry
                    $waitingRetry = RecipientLog::where('campaign_id', $campaignId)
                        ->where('delivery_status', 'queued')
                        ->where('decision', 'approved')
                        ->where('next_retry_at', '>', $now)
                        ->exists();
                }

                if (!$waitingRetry) {
                    // No more queued recipients and none waiting for retry, mark completed
                    $campaign->update(['status' => 'completed']);
                }
                continue;
            }

            $fromEmail = $campaign->from_address ?: config('pabbly.from_email', 'contact@mailer-b2bexportsllc.com');
            if ($fromEmail === 'rma@proitbuyer.com' || str_ends_with($fromEmail, '@proitbuyer.com')) {
                $fromEmail = config('pabbly.from_email', 'contact@mailer-b2bexportsllc.com');
            }
            $fromName = config('pabbly.from_name', 'anil patel');
            $deliveryServerId = config('pabbly.delivery_server_id', 'send-with-us');

            $campaignHaltedDueToCooldown = false;

            foreach ($recipients as $recipient) {
                // Check execution limits
                if ($maxEmails !== null && $processedCount >= $maxEmails) {
                    break 2;
                }
                if ($maxExecutionSeconds !== null && (microtime(true) - $startTime) >= $maxExecutionSeconds) {
                    break 2;
                }

                // Check Pabbly cooldown before each recipient
                if ($this->rateLimiter->isInCooldown($pabblyApiKey)) {
                    $campaignHaltedDueToCooldown = true;
                    break;
                }

                // CONCURRENCY & DUPLICATE DISPATCH SHIELD
                // Acquire an atomic lock on this recipient record (expires in 60s)
                $recipientLock = Cache::lock("pabbly:recipient_lock:{$recipient->id}", 60);
                if (!$recipientLock->get()) {
                    // Another concurrent worker/process is actively processing this recipient
                    continue;
                }

                try {
                    // Fresh database check: Ensure recipient was not already sent or modified
                    $recipient->refresh();
                    if ($recipient->delivery_status !== 'queued') {
                        continue;
                    }
                    if ($recipient->sent_at !== null) {
                        continue;
                    }
                    if ($this->hasRetryColumns() && $recipient->next_retry_at && $recipient->next_retry_at->isFuture()) {
                        continue;
                    }

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

                        $campaign->decrement('total_approved');
                        $campaign->increment('total_blocked');
                        continue;
                    }

                    // Render complete delivered email (subject, body, signature, attachments, unsubscribe footer, pixel)
                    $rendered = $this->emailRenderer->render(
                        $campaign,
                        $sfRecord ?: $recipient,
                        $campaign->user,
                        includeTrackingPixel: true,
                        overrideTrackingToken: $recipient->tracking_token
                    );

                    $personalizedSubject = $rendered['subject'];
                    $finalBody = $rendered['html'];
                    $recipientName = $rendered['recipient_name'];

                    $providerMsgId = 'msg-' . Str::random(16);
                    $sentAt = Carbon::now();

                    // Send via Pabbly if configured, or simulate if testing
                    $usePabbly = !empty($pabblyApiKey) && (!app()->environment('testing') || config('pabbly.force_pabbly_in_testing', false));

                    if ($usePabbly) {
                        $existingPabblyId = $recipient->provider_message_id;
                        if ($existingPabblyId && str_starts_with($existingPabblyId, 'msg-')) {
                            $existingPabblyId = null;
                        }

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
                                $replyToEmail,
                                existingPabblyCampaignId: $existingPabblyId
                            );

                            $providerMsgId = $pabblyResult['pabbly_campaign_id'] ?? $providerMsgId;

                            $sentPayload = [
                                'delivery_status' => 'sent',
                                'provider_message_id' => $providerMsgId,
                                'error_message' => null,
                                'sent_at' => $sentAt,
                                'validated_at' => $sentAt,
                            ];
                            if ($this->hasRetryColumns()) {
                                $sentPayload['next_retry_at'] = null;
                            }
                            $recipient->update($sentPayload);

                            if ($recipient->campaign_member_id) {
                                \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                                    'status' => 'sent',
                                ]);
                            }

                            $processedCount++;
                        } catch (PabblyRateLimitException $e) {
                            $this->handleRateLimitFailure($recipient, $e, $campaignId, $pabblyApiKey);
                            $campaignHaltedDueToCooldown = true;
                            break;
                        } catch (PabblyApiException $e) {
                            if ($e->isRetryable()) {
                                $this->handleRetryableFailure($recipient, $e, $campaignId, $pabblyApiKey);
                                $campaignHaltedDueToCooldown = true;
                                break;
                            } else {
                                $this->handlePermanentFailure($recipient, $e);
                            }
                        } catch (Throwable $e) {
                            if (stripos($e->getMessage(), 'Too many requests') !== false || stripos($e->getMessage(), 'rate limit') !== false) {
                                $rateLimitEx = new PabblyRateLimitException($e->getMessage(), 429);
                                $this->handleRateLimitFailure($recipient, $rateLimitEx, $campaignId, $pabblyApiKey);
                                $campaignHaltedDueToCooldown = true;
                                break;
                            } else {
                                $this->handlePermanentFailure($recipient, $e);
                            }
                        }
                    } else {
                        // Simulated dispatch (for testing environment)
                        $simPayload = [
                            'delivery_status' => 'sent',
                            'provider_message_id' => $providerMsgId,
                            'sent_at' => $sentAt,
                            'validated_at' => $sentAt,
                            'error_message' => null,
                        ];
                        if ($this->hasRetryColumns()) {
                            $simPayload['next_retry_at'] = null;
                        }
                        $recipient->update($simPayload);

                        if ($recipient->campaign_member_id) {
                            \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                                'status' => 'sent',
                            ]);
                        }

                        $processedCount++;
                    }
                } finally {
                    $recipientLock->release();
                }
            }

            // Check if all queued recipients for this campaign have finished (unless paused due to rate-limit cooldown)
            if (!$campaignHaltedDueToCooldown) {
                $remaining = RecipientLog::where('campaign_id', $campaignId)
                    ->where('delivery_status', 'queued')
                    ->where('decision', 'approved')
                    ->count();

                if ($remaining === 0) {
                    $campaign->update(['status' => 'completed']);
                }
            }
        }

        return $processedCount;
    }

    /**
     * Handle temporary rate-limiting (429) failures with exponential backoff and retry scheduling.
     */
    protected function handleRateLimitFailure(
        RecipientLog $recipient,
        PabblyRateLimitException $e,
        string $campaignId,
        string $apiKey
    ): void {
        $maxRetries = (int) config('pabbly.max_retry_attempts', 5);
        $currentRetries = (int) $recipient->retry_count;
        $pabblyCampaignId = $e->getPabblyCampaignId() ?: $recipient->provider_message_id;

        $delay = $this->rateLimiter->calculateBackoffDelay($currentRetries + 1, $e->getRetryAfter());
        $nextRetryAt = Carbon::now()->addSeconds($delay);

        if ($currentRetries < $maxRetries) {
            $newRetries = $currentRetries + 1;
            $msg = "Rate limit hit (HTTP 429). Attempt {$newRetries}/{$maxRetries} scheduled for retry at {$nextRetryAt->format('Y-m-d H:i:s')} (cooldown {$delay}s). Provider: {$e->getMessage()}";

            $updateData = [
                'delivery_status' => 'queued',
                'error_message' => $msg,
                'validated_at' => Carbon::now(),
            ];
            if ($this->hasRetryColumns()) {
                $updateData['retry_count'] = $newRetries;
                $updateData['next_retry_at'] = $nextRetryAt;
            }
            if ($pabblyCampaignId) {
                $updateData['provider_message_id'] = $pabblyCampaignId;
            }

            $recipient->update($updateData);

            if ($recipient->campaign_member_id) {
                \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                    'status' => 'queued',
                ]);
            }

            $this->rateLimiter->setCooldown($apiKey, $delay, "Rate limit hit on {$recipient->email} (attempt {$newRetries}/{$maxRetries})");

            Log::warning("[CampaignProcessing] Recipient {$recipient->email} (campaign {$campaignId}) rate-limited. Retry {$newRetries}/{$maxRetries} scheduled at {$nextRetryAt->format('Y-m-d H:i:s')}.");
        } else {
            $msg = "Pabbly dispatch failed: Rate limit retry attempts exhausted ({$maxRetries}/{$maxRetries}). {$e->getMessage()}";
            $updateData = [
                'delivery_status' => 'failed',
                'error_message' => $msg,
                'validated_at' => Carbon::now(),
            ];
            if ($pabblyCampaignId) {
                $updateData['provider_message_id'] = $pabblyCampaignId;
            }

            $recipient->update($updateData);

            if ($recipient->campaign_member_id) {
                \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                    'status' => 'failed',
                ]);
            }

            Log::error("[CampaignProcessing] Recipient {$recipient->email} (campaign {$campaignId}) permanently failed after {$maxRetries} rate limit retries.");
        }
    }

    /**
     * Handle temporary 5xx server or connection failures with exponential backoff.
     */
    protected function handleRetryableFailure(
        RecipientLog $recipient,
        PabblyApiException $e,
        string $campaignId,
        string $apiKey
    ): void {
        $maxRetries = (int) config('pabbly.max_retry_attempts', 5);
        $currentRetries = (int) $recipient->retry_count;
        $delay = $this->rateLimiter->calculateBackoffDelay($currentRetries + 1, $e->getRetryAfter());
        $nextRetryAt = Carbon::now()->addSeconds($delay);

        if ($currentRetries < $maxRetries) {
            $newRetries = $currentRetries + 1;
            $msg = "Temporary provider error ({$e->getStatusCode()}). Attempt {$newRetries}/{$maxRetries} scheduled for retry at {$nextRetryAt->format('Y-m-d H:i:s')}. Provider: {$e->getMessage()}";

            $updateData = [
                'delivery_status' => 'queued',
                'error_message' => $msg,
                'validated_at' => Carbon::now(),
            ];
            if ($this->hasRetryColumns()) {
                $updateData['retry_count'] = $newRetries;
                $updateData['next_retry_at'] = $nextRetryAt;
            }

            $recipient->update($updateData);

            if ($recipient->campaign_member_id) {
                \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                    'status' => 'queued',
                ]);
            }

            $this->rateLimiter->setCooldown($apiKey, min(30, $delay), "Temporary 5xx provider error on {$recipient->email}");
            Log::warning("[CampaignProcessing] Temporary provider error for {$recipient->email} (status {$e->getStatusCode()}). Retry {$newRetries}/{$maxRetries} scheduled at {$nextRetryAt->format('Y-m-d H:i:s')}.");
        } else {
            $this->handlePermanentFailure($recipient, $e);
        }
    }

    /**
     * Handle permanent non-retryable provider failure (e.g. 400 Bad Request, 401 Unauthorized, 403, 404, 422).
     */
    protected function handlePermanentFailure(RecipientLog $recipient, Throwable $e): void
    {
        $errorMsg = $e->getMessage();
        Log::error("[CampaignProcessing] Failed to send email to {$recipient->email} via Pabbly: " . $errorMsg);

        $recipient->update([
            'delivery_status' => 'failed',
            'error_message' => $errorMsg,
            'validated_at' => Carbon::now(),
        ]);

        if ($recipient->campaign_member_id) {
            \App\Models\CampaignMember::where('id', $recipient->campaign_member_id)->update([
                'status' => 'failed',
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\GlobalSuppression;
use App\Models\RecipientLog;
use App\Models\RecipientOpen;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PabblyWebhookService
{
    public function __construct(
        protected SalesforceService $sfService
    ) {}

    /**
     * Process incoming Pabbly Webhook Payload.
     */
    public function processPayload(array $payload): array
    {
        // Normalize Event Type
        $eventType = strtolower(trim(
            $payload['event_type'] 
            ?? $payload['event'] 
            ?? $payload['type'] 
            ?? $payload['action'] 
            ?? 'unknown'
        ));

        // Normalize Recipient Email
        $rawEmail = $payload['data']['subscriber']['email'] 
            ?? $payload['data']['email'] 
            ?? $payload['email'] 
            ?? $payload['recipient_email'] 
            ?? null;

        $email = $rawEmail ? strtolower(trim((string) $rawEmail)) : null;
        $campaignId = $payload['data']['campaign_id'] ?? $payload['campaign_id'] ?? null;
        $messageId = $payload['data']['message_id'] ?? $payload['message_id'] ?? null;

        return match ($eventType) {
            'email_unsubscribed', 'unsubscribed', 'unsubscribe', 'subscriber_unsubscribed' => 
                $this->handleUnsubscribe($email, $campaignId, $messageId, $payload),

            'email_delivered', 'delivered', 'delivery' => 
                $this->handleDelivered($email, $messageId, $payload),

            'email_opened', 'opened', 'open' => 
                $this->handleOpened($email, $messageId, $payload),

            'email_bounced', 'bounced', 'bounce' => 
                $this->handleBounce($email, $messageId, $payload),

            default => [
                'success' => true,
                'message' => "Event '{$eventType}' ignored or unhandled.",
                'event_type' => $eventType,
            ]
        };
    }

    /**
     * Idempotent Unsubscribe Handler.
     */
    protected function handleUnsubscribe(?string $email, ?string $campaignId, ?string $messageId, array $payload): array
    {
        if (empty($email)) {
            Log::warning('[PABBLY WEBHOOK] Unsubscribe received without email address.');
            return [
                'success' => false,
                'error' => 'Email address missing from payload.',
            ];
        }

        DB::transaction(function () use ($email, $campaignId, $messageId, $payload) {
            // 1. Add to Global Suppression List (Idempotent)
            GlobalSuppression::recordUnsubscribe(
                email: $email,
                reason: 'Unsubscribed via Pabbly Webhook',
                source: 'pabbly_webhook',
                campaignId: $campaignId,
                providerMessageId: $messageId,
                metadata: $payload
            );

            // 2. Update Recipient Log Delivery Status (Idempotent)
            $query = RecipientLog::where('email', $email);
            if (!empty($messageId)) {
                $query->where('provider_message_id', $messageId);
            }

            $logs = $query->get();

            // If no log found by specific message ID, fallback to all recent logs for this email
            if ($logs->isEmpty() && !empty($messageId)) {
                $logs = RecipientLog::where('email', $email)->get();
            }

            foreach ($logs as $log) {
                $log->update(['delivery_status' => 'unsubscribed']);

                // 3. Sync opt-out status to local Salesforce Lead/Contact
                if ($log->salesforce_record_id && $log->salesforce_record_id !== 'N/A') {
                    $this->sfService->updateSalesforceRecord(
                        $log->salesforce_record_id, 
                        ['opted_out' => true]
                    );
                }
            }
        });

        Log::info("[PABBLY WEBHOOK] Unsubscribed: {$email}", [
            'campaign_id' => $campaignId,
            'message_id' => $messageId,
        ]);

        return [
            'success' => true,
            'event' => 'unsubscribed',
            'email' => $email,
            'message' => "Recipient {$email} permanently added to suppression list.",
        ];
    }

    /**
     * Delivery Handler.
     */
    protected function handleDelivered(?string $email, ?string $messageId, array $payload): array
    {
        if ($messageId) {
            RecipientLog::where('provider_message_id', $messageId)
                ->whereIn('delivery_status', ['sent', 'queued'])
                ->update(['delivery_status' => 'delivered']);
        } elseif ($email) {
            RecipientLog::where('email', $email)
                ->whereIn('delivery_status', ['sent', 'queued'])
                ->latest()
                ->first()
                ?->update(['delivery_status' => 'delivered']);
        }

        Log::info("[PABBLY WEBHOOK] Delivered: {$email}");

        return ['success' => true, 'event' => 'delivered', 'email' => $email];
    }

    /**
     * Open Handler.
     */
    protected function handleOpened(?string $email, ?string $messageId, array $payload): array
    {
        $log = null;
        if ($messageId) {
            $log = RecipientLog::where('provider_message_id', $messageId)->first();
        } elseif ($email) {
            $log = RecipientLog::where('email', $email)->latest()->first();
        }

        if ($log) {
            if (in_array($log->delivery_status, ['sent', 'delivered'])) {
                $log->update(['delivery_status' => 'opened']);
            }

            RecipientOpen::create([
                'recipient_log_id' => $log->id,
                'opened_at' => Carbon::now(),
                'ip_address' => request()->ip() ?? 'Pabbly-Webhook',
                'user_agent' => 'Pabbly Webhook',
            ]);
        }

        Log::info("[PABBLY WEBHOOK] Opened: {$email}");

        return ['success' => true, 'event' => 'opened', 'email' => $email];
    }

    /**
     * Bounce Handler.
     */
    protected function handleBounce(?string $email, ?string $messageId, array $payload): array
    {
        if ($messageId) {
            RecipientLog::where('provider_message_id', $messageId)
                ->update([
                    'delivery_status' => 'bounce',
                    'error_message' => $payload['data']['reason'] ?? ($payload['reason'] ?? 'Bounced via Pabbly'),
                ]);
        } elseif ($email) {
            RecipientLog::where('email', $email)
                ->latest()
                ->first()
                ?->update([
                    'delivery_status' => 'bounce',
                    'error_message' => $payload['data']['reason'] ?? ($payload['reason'] ?? 'Bounced via Pabbly'),
                ]);
        }

        Log::warning("[PABBLY WEBHOOK] Bounced: {$email}");

        return ['success' => true, 'event' => 'bounce', 'email' => $email];
    }
}

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
        protected SalesforceService $sfService,
        protected EmailSuppressionService $suppressionService
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

            'spam_complaint', 'complaint', 'abuse' => 
                $this->handleComplaint($email, $campaignId, $messageId, $payload),

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

        $externalEventId = $payload['event_id'] ?? $payload['id'] ?? null;

        DB::transaction(function () use ($email, $campaignId, $messageId, $externalEventId, $payload) {
            // 1. Add to Global Suppression List via EmailSuppressionService (Idempotent)
            $this->suppressionService->suppress(
                email: $email,
                reason: 'Unsubscribed via Pabbly Webhook',
                source: 'pabbly_webhook',
                campaignId: $campaignId,
                messageId: $messageId,
                externalEventId: $externalEventId,
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
            'email' => strtolower(trim($email)),
            'message' => "Recipient {$email} permanently added to suppression list.",
        ];
    }

    /**
     * Complaint / Spam Abuse Handler.
     */
    protected function handleComplaint(?string $email, ?string $campaignId, ?string $messageId, array $payload): array
    {
        if (empty($email)) {
            Log::warning('[PABBLY WEBHOOK] Complaint received without email address.');
            return ['success' => false, 'error' => 'Email address missing from payload.'];
        }

        $externalEventId = $payload['event_id'] ?? $payload['id'] ?? null;

        DB::transaction(function () use ($email, $campaignId, $messageId, $externalEventId, $payload) {
            $this->suppressionService->suppress(
                email: $email,
                reason: 'Spam Complaint via Pabbly Webhook',
                source: 'pabbly_webhook',
                campaignId: $campaignId,
                messageId: $messageId,
                externalEventId: $externalEventId,
                metadata: $payload
            );

            RecipientLog::where('email', $email)->update([
                'delivery_status' => 'spam_complaint',
                'decision' => 'blocked',
                'decision_reason' => 'COMPLIANCE_RULE',
            ]);
        });

        Log::warning("[PABBLY WEBHOOK] Spam Complaint: {$email}", [
            'campaign_id' => $campaignId,
            'message_id' => $messageId,
        ]);

        return [
            'success' => true,
            'event' => 'complaint',
            'email' => strtolower(trim($email)),
            'message' => "Recipient {$email} suppressed due to spam complaint.",
        ];
    }

    /**
     * Delivery Handler.
     */
    protected function handleDelivered(?string $email, ?string $messageId, array $payload): array
    {
        $log = null;
        if ($messageId) {
            $log = RecipientLog::where('provider_message_id', $messageId)->first();
        } elseif ($email) {
            $log = RecipientLog::where('email', $email)->latest()->first();
        }

        if ($log) {
            $country = $payload['data']['country'] ?? ($payload['data']['subscriber']['country'] ?? ($payload['country'] ?? null));
            $region = $payload['data']['region'] ?? ($payload['data']['state'] ?? ($payload['data']['subscriber']['state'] ?? ($payload['region'] ?? ($payload['state'] ?? null))));
            $city = $payload['data']['city'] ?? ($payload['data']['subscriber']['city'] ?? ($payload['city'] ?? null));

            if (!$country || !$city) {
                $resolved = $this->resolveRecipientLocation($log);
                $country = $country ?: ($resolved['country'] ?? null);
                $region = $region ?: ($resolved['region'] ?? null);
                $city = $city ?: ($resolved['city'] ?? null);
            }

            $updates = [];
            if (in_array($log->delivery_status, ['sent', 'queued'])) {
                $updates['delivery_status'] = 'delivered';
            }
            if ($country && empty($log->country)) {
                $updates['country'] = $country;
            }
            if ($region && empty($log->region)) {
                $updates['region'] = $region;
            }
            if ($city && empty($log->city)) {
                $updates['city'] = $city;
            }

            if (!empty($updates)) {
                $log->update($updates);
            }
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
            $country = $payload['data']['country'] ?? ($payload['data']['subscriber']['country'] ?? ($payload['country'] ?? null));
            $region = $payload['data']['region'] ?? ($payload['data']['state'] ?? ($payload['data']['subscriber']['state'] ?? ($payload['region'] ?? ($payload['state'] ?? null))));
            $city = $payload['data']['city'] ?? ($payload['data']['subscriber']['city'] ?? ($payload['city'] ?? null));

            if (!$country || !$city) {
                $resolved = $this->resolveRecipientLocation($log);
                $country = $country ?: ($resolved['country'] ?? null);
                $region = $region ?: ($resolved['region'] ?? null);
                $city = $city ?: ($resolved['city'] ?? null);
            }

            $logUpdates = [];
            if (in_array($log->delivery_status, ['sent', 'delivered'])) {
                $logUpdates['delivery_status'] = 'opened';
            }
            if ($country && empty($log->country)) {
                $logUpdates['country'] = $country;
            }
            if ($region && empty($log->region)) {
                $logUpdates['region'] = $region;
            }
            if ($city && empty($log->city)) {
                $logUpdates['city'] = $city;
            }

            if (!empty($logUpdates)) {
                $log->update($logUpdates);
            }

            RecipientOpen::create([
                'recipient_log_id' => $log->id,
                'opened_at' => Carbon::now(),
                'ip_address' => request()->ip() ?? ($payload['data']['ip'] ?? ($payload['ip'] ?? 'Pabbly-Webhook')),
                'user_agent' => 'Pabbly Webhook',
                'country' => $country,
                'region' => $region,
                'city' => $city,
            ]);
        }

        Log::info("[PABBLY WEBHOOK] Opened: {$email}");

        return ['success' => true, 'event' => 'opened', 'email' => $email];
    }

    /**
     * Resolve recipient location from Salesforce contact, lead, or mock record.
     */
    protected function resolveRecipientLocation(RecipientLog $log): array
    {
        // 1. If log already has location
        if (!empty($log->country)) {
            return [
                'country' => $log->country,
                'region' => $log->region,
                'city' => $log->city,
            ];
        }

        // 2. Lookup Contact
        if ($log->salesforce_record_id && $log->salesforce_object === 'Contact') {
            $contact = DB::table('salesforce_contacts')
                ->where('salesforce_id', $log->salesforce_record_id)
                ->first();
            if ($contact && (!empty($contact->mailing_country) || !empty($contact->mailing_city))) {
                return [
                    'country' => $contact->mailing_country ?: '',
                    'region' => $contact->mailing_state ?: '',
                    'city' => $contact->mailing_city ?: '',
                ];
            }
        }

        // 3. Lookup Lead
        if ($log->salesforce_record_id && $log->salesforce_object === 'Lead') {
            $lead = DB::table('salesforce_leads')
                ->where('salesforce_id', $log->salesforce_record_id)
                ->first();
            if ($lead && (!empty($lead->country) || !empty($lead->city))) {
                return [
                    'country' => $lead->country ?: '',
                    'region' => $lead->state ?: '',
                    'city' => $lead->city ?: '',
                ];
            }
        }

        // 4. Lookup by email across contacts / leads / mock
        if (!empty($log->email)) {
            $contactByEmail = DB::table('salesforce_contacts')
                ->where('email', $log->email)
                ->first();
            if ($contactByEmail && (!empty($contactByEmail->mailing_country) || !empty($contactByEmail->mailing_city))) {
                return [
                    'country' => $contactByEmail->mailing_country ?: '',
                    'region' => $contactByEmail->mailing_state ?: '',
                    'city' => $contactByEmail->mailing_city ?: '',
                ];
            }

            $leadByEmail = DB::table('salesforce_leads')
                ->where('email', $log->email)
                ->first();
            if ($leadByEmail && (!empty($leadByEmail->country) || !empty($leadByEmail->city))) {
                return [
                    'country' => $leadByEmail->country ?: '',
                    'region' => $leadByEmail->state ?: '',
                    'city' => $leadByEmail->city ?: '',
                ];
            }

            $mockByEmail = DB::table('salesforce_mock_records')
                ->where('email', $log->email)
                ->first();
            if ($mockByEmail && !empty($mockByEmail->country)) {
                return [
                    'country' => $mockByEmail->country ?: '',
                    'region' => $mockByEmail->region ?: '',
                    'city' => '',
                ];
            }
        }

        // 5. Lookup mock by record ID
        if ($log->salesforce_record_id) {
            $mock = DB::table('salesforce_mock_records')
                ->where('id', $log->salesforce_record_id)
                ->first();
            if ($mock && !empty($mock->country)) {
                return [
                    'country' => $mock->country ?: '',
                    'region' => $mock->region ?: '',
                    'city' => '',
                ];
            }
        }

        return ['country' => null, 'region' => null, 'city' => null];
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

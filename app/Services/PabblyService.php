<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PabblyService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $deliveryServerId;
    protected string $fromEmail;
    protected string $fromName;
    protected string $replyTo;

    public function __construct()
    {
        $this->apiKey = config('pabbly.api_key', '');
        $this->baseUrl = config('pabbly.base_url', 'https://emails.pabbly.com/api/v2');
        $this->deliveryServerId = config('pabbly.delivery_server_id', 'send-with-us');
        $this->fromEmail = config('pabbly.from_email', 'rma@proitbuyer.com');
        $this->fromName = config('pabbly.from_name', 'anil patel');
        $this->replyTo = config('pabbly.reply_to', 'support@b2bexportsllc.com');
    }

    /**
     * Send an email to a single recipient through Pabbly Email Marketing API.
     */
    public function sendEmail(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $htmlBody,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?string $deliveryServerId = null,
        ?string $replyTo = null
    ): array {
        return $this->sendBulk(
            [$recipientEmail],
            $subject,
            $htmlBody,
            $fromEmail,
            $fromName,
            $deliveryServerId,
            $replyTo
        );
    }

    /**
     * Create a campaign in Pabbly and dispatch to one or more recipient emails.
     */
    public function sendBulk(
        array $recipientEmails,
        string $subject,
        string $htmlBody,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?string $deliveryServerId = null,
        ?string $replyTo = null
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PABBLY_API_KEY is not configured in .env');
        }

        $senderEmail = $fromEmail ?: $this->fromEmail;
        $senderName = $fromName ?: $this->fromName;
        $serverId = $deliveryServerId ?: $this->deliveryServerId;
        $replyToEmail = $replyTo ?: $this->replyTo;
        $campaignName = 'Campaign - ' . substr($subject, 0, 30) . ' (' . date('Y-m-d H:i:s') . ')';

        // 1. Create Campaign in Pabbly
        $campaignPayload = [
            'campaignDetails' => [
                'campaignName' => $campaignName,
                'senderName' => $senderName,
                'subject' => $subject,
                'replyToEmail' => $replyToEmail,
                'replyTo' => $replyToEmail,
                'preheaderText' => substr(strip_tags($htmlBody), 0, 100),
            ],
            'builderType' => 'HTML',
            'content' => $htmlBody,
        ];

        $createResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("{$this->baseUrl}/campaigns", $campaignPayload);

        if (!$createResponse->successful()) {
            $err = $createResponse->json('message') ?? $createResponse->json('error') ?? $createResponse->body();
            Log::error("[PabblyService] Failed to create campaign: {$err}");
            throw new RuntimeException("Pabbly campaign creation failed: {$err}");
        }

        $createData = $createResponse->json();
        $pabblyCampaignId = $createData['data']['_id'] ?? $createData['data']['id'] ?? null;

        if (!$pabblyCampaignId) {
            throw new RuntimeException('Pabbly created campaign but returned no campaign ID.');
        }

        // 2. Dispatch to recipients via send-to-individual
        $sendPayload = [
            'campaignId' => $pabblyCampaignId,
            'emails' => array_values($recipientEmails),
            'senderEmail' => $senderEmail,
            'fromName' => $senderName,
            'replyToEmail' => $replyToEmail,
            'replyTo' => $replyToEmail,
            'subject' => $subject,
        ];

        if (!empty($serverId)) {
            $sendPayload['deliveryServerId'] = $serverId;
        }

        $sendResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("{$this->baseUrl}/campaigns/send-to-individual", $sendPayload);

        if (!$sendResponse->successful()) {
            $err = $sendResponse->json('message') ?? $sendResponse->json('error') ?? $sendResponse->body();
            Log::error("[PabblyService] Failed to send campaign {$pabblyCampaignId}: {$err}");
            throw new RuntimeException("Pabbly dispatch failed: {$err}");
        }

        $sendData = $sendResponse->json();

        Log::info("[PabblyService] Dispatched campaign {$pabblyCampaignId} to " . count($recipientEmails) . " recipient(s).");

        return [
            'success' => true,
            'pabbly_campaign_id' => $pabblyCampaignId,
            'queued' => $sendData['data']['queued'] ?? count($recipientEmails),
            'response' => $sendData,
        ];
    }

    /**
     * Get campaign details and delivery statistics from Pabbly API.
     */
    public function getCampaignDetails(string $pabblyCampaignId): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(15)->get("{$this->baseUrl}/campaigns/{$pabblyCampaignId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("[PabblyService] getCampaignDetails returned status {$response->status()}: " . $response->body());
            return null;
        } catch (Exception $e) {
            Log::error("[PabblyService] getCampaignDetails exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get subscriber statistics and engagement from Pabbly API.
     */
    public function getSubscriberStats(string $subscriberIdOrEmail): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(15)->get("{$this->baseUrl}/subscribers/{$subscriberIdOrEmail}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error("[PabblyService] getSubscriberStats exception: " . $e->getMessage());
            return null;
        }
    }
}


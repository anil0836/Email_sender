<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PabblyService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $deliveryServerId;
    protected string $fromEmail;
    protected string $fromName;
    protected string $replyTo;
    protected static array $generatedCampaignNames = [];

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
        ?string $replyTo = null,
        ?string $campaignName = null
    ): array {
        return $this->sendBulk(
            [$recipientEmail],
            $subject,
            $htmlBody,
            $fromEmail,
            $fromName,
            $deliveryServerId,
            $replyTo,
            $campaignName
        );
    }

    /**
     * Generate a campaign name by inserting randomized spacing between words and letters
     * of the subject to ensure uniqueness within Pabbly while maintaining a natural appearance.
     *
     * Example:
     * 1) "this is product of sale"
     * 2) "this  is product   of sale "
     */
    public function generateUniqueCampaignName(string $subject, ?string $customCampaignName = null, int $attempt = 1): string
    {
        $base = !empty($customCampaignName) ? trim($customCampaignName) : trim($subject);
        if (empty($base)) {
            $base = 'Campaign';
        }

        // Limit the base text to 60 characters so spacing does not exceed Pabbly limits
        $clean = preg_replace('/\s+/u', ' ', $base);
        $clean = Str::limit($clean, 60, '');
        $words = explode(' ', $clean);

        for ($try = 0; $try < 100; $try++) {
            if (count($words) > 1) {
                $parts = [];
                $maxSpaces = min(4 + $attempt, 7);
                foreach ($words as $idx => $word) {
                    // On retries or for long words, optionally insert space inside letters
                    if ($attempt > 1 && mb_strlen($word) > 4 && rand(0, 1) === 1) {
                        $pos = rand(1, mb_strlen($word) - 1);
                        $word = mb_substr($word, 0, $pos) . str_repeat(' ', rand(1, 2)) . mb_substr($word, $pos);
                    }
                    $parts[] = $word;
                    if ($idx < count($words) - 1) {
                        $parts[] = str_repeat(' ', rand(1, $maxSpaces));
                    }
                }
                $candidate = implode('', $parts);
            } else {
                // Single word: inject spaces between letters
                $letters = preg_split('//u', $clean, -1, PREG_SPLIT_NO_EMPTY);
                $parts = [];
                foreach ($letters as $idx => $char) {
                    $parts[] = $char;
                    if ($idx < count($letters) - 1) {
                        $spaces = rand(0, 10) > 4 ? rand(1, 2 + $attempt) : (rand(0, 1) ? 1 : 0);
                        if ($spaces > 0) {
                            $parts[] = str_repeat(' ', $spaces);
                        }
                    }
                }
                $candidate = implode('', $parts);
                if ($candidate === $clean) {
                    $pos = rand(1, max(1, count($letters) - 1));
                    $candidate = mb_substr($clean, 0, $pos) . str_repeat(' ', rand(1, 3)) . mb_substr($clean, $pos);
                }
            }

            if (!isset(self::$generatedCampaignNames[$candidate])) {
                self::$generatedCampaignNames[$candidate] = true;
                return $candidate;
            }
        }

        $fallback = $clean . str_repeat(' ', rand(1, 4 + $attempt));
        self::$generatedCampaignNames[$fallback] = true;
        return $fallback;
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
        ?string $replyTo = null,
        ?string $campaignName = null
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PABBLY_API_KEY is not configured in .env');
        }

        $senderEmail = $fromEmail ?: $this->fromEmail;
        $senderName = $fromName ?: $this->fromName;
        $serverId = $deliveryServerId ?: $this->deliveryServerId;
        $replyToEmail = $replyTo ?: $this->replyTo;

        // 1. Create Campaign in Pabbly with uniqueness protection & retry on name collision
        $maxAttempts = 3;
        $pabblyCampaignId = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $generatedCampaignName = $this->generateUniqueCampaignName($subject, $campaignName, $attempt);

            $campaignPayload = [
                'campaignDetails' => [
                    'campaignName' => $generatedCampaignName,
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

            if ($createResponse->successful()) {
                $createData = $createResponse->json();
                $pabblyCampaignId = $createData['data']['_id'] ?? $createData['data']['id'] ?? null;
                if ($pabblyCampaignId) {
                    break;
                }
            }

            $err = $createResponse->json('message') ?? $createResponse->json('error') ?? $createResponse->body();

            // Self-healing: if campaign name collides within business, regenerate and retry
            if ($attempt < $maxAttempts && (
                stripos($err, 'campaign name must be unique') !== false ||
                stripos($err, 'already exists') !== false
            )) {
                Log::warning("[PabblyService] Duplicate campaign name collision on attempt {$attempt} ('{$generatedCampaignName}'): {$err}. Retrying with fresh unique name...");
                usleep(100000); // 100ms jitter
                continue;
            }

            Log::error("[PabblyService] Failed to create campaign: {$err}");
            throw new RuntimeException("Pabbly campaign creation failed: {$err}");
        }

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


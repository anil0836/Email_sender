<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\EmailAttachment;
use App\Models\RecipientLog;
use App\Models\User;
use App\Models\UserSignature;
use Illuminate\Database\Eloquent\Model;

class EmailRendererService
{
    public function __construct(
        protected CampaignMergeFieldService $mergeFieldService,
        protected SalesforceService $sfService,
        protected ?AttachmentService $attachmentService = null
    ) {
        $this->attachmentService = $attachmentService ?: app(AttachmentService::class);
    }

    /**
     * Render the complete delivered email for a campaign and recipient.
     *
     * @param Campaign $campaign
     * @param RecipientLog|CampaignMember|array|null $recipient
     * @param User|null $sender
     * @param bool $includeTrackingPixel
     * @param string|null $overrideTrackingToken
     * @return array
     */
    public function render(
        Campaign $campaign,
        RecipientLog|CampaignMember|array|null $recipient = null,
        ?User $sender = null,
        bool $includeTrackingPixel = false,
        ?string $overrideTrackingToken = null
    ): array {
        // 1. Resolve Sender User
        $senderUser = $sender ?: ($campaign->user ?: User::find($campaign->user_id));

        $fromEmail = $campaign->from_address ?: ($senderUser?->email ?: config('pabbly.from_email', 'contact@mailer-b2bexportsllc.com'));
        if ($fromEmail === 'rma@proitbuyer.com' || str_ends_with($fromEmail, '@proitbuyer.com')) {
            $fromEmail = config('pabbly.from_email', 'contact@mailer-b2bexportsllc.com');
        }
        $fromName = config('pabbly.from_name') ?: ($senderUser ? ($senderUser->name ?: $senderUser->username) : 'anil patel');
        $replyTo = trim((string) ($campaign->reply_to ?: ($senderUser?->email ?: config('pabbly.reply_to', 'support@b2bexportsllc.com'))));

        // 2. Resolve Recipient Context (CRM data, email, tracking token, recipient ID)
        $recipientResolution = $this->resolveRecipientContext($campaign, $recipient, $overrideTrackingToken);
        $sfRecord = $recipientResolution['sf_record'];
        $recipientEmail = $recipientResolution['email'];
        $trackingToken = $recipientResolution['tracking_token'];
        $recipientLogId = $recipientResolution['recipient_log_id'];
        $isSample = $recipientResolution['is_sample'];

        // 3. Resolve Sender Signature
        $signatureHtml = $this->resolveSignatureHtml($campaign, $senderUser);

        // 4. Resolve Subject with merge fields
        $personalizedSubject = $this->mergeFieldService->resolve(
            $campaign->subject ?? '',
            $sfRecord,
            $senderUser,
            null,
            false
        );

        // 5. Resolve Body with merge fields and signature
        $personalizedBody = $this->mergeFieldService->resolve(
            $campaign->body ?? '',
            $sfRecord,
            $senderUser,
            $signatureHtml,
            true // autoAppendSignature if {{Signature}} not explicitly placed
        );

        // 6. Build Attachment Section
        $attachmentData = $this->buildAttachmentSection($campaign);
        $attachmentHtml = $attachmentData['html'];

        // 7. Build Unsubscribe Footer
        $unsubscribeFooter = $this->buildUnsubscribeFooter($recipientEmail, $trackingToken);

        // 8. Build Tracking Pixel (only enabled during live sending, never in preview)
        $pixelTag = '';
        if ($includeTrackingPixel && !empty($trackingToken) && $trackingToken !== 'preview-token') {
            $baseUrl = rtrim(config('app.url'), '/');
            $pixelUrl = "{$baseUrl}/track/open/{$trackingToken}";
            $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;" />';
        }

        // 9. Assemble Final HTML Body (as delivered to recipient inbox)
        $finalHtml = $personalizedBody . $attachmentHtml . $unsubscribeFooter . $pixelTag;

        // 10. Wrap in clean isolated HTML document for iframe preview
        $fullDocument = $this->wrapInHtmlDocument($finalHtml, $personalizedSubject);

        $recipientDisplayName = $sfRecord['name'] ?? trim(($sfRecord['first_name'] ?? '') . ' ' . ($sfRecord['last_name'] ?? ''));
        if (empty($recipientDisplayName)) {
            $recipientDisplayName = $recipientEmail ?: 'Valued Customer';
        }

        return [
            'subject' => $personalizedSubject,
            'html' => $finalHtml,
            'full_document' => $fullDocument,
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'reply_to' => $replyTo,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientDisplayName,
            'recipient_log_id' => $recipientLogId,
            'tracking_token' => $trackingToken,
            'has_attachments' => $attachmentData['count'] > 0,
            'attachments_count' => $attachmentData['count'],
            'attachments_list' => $attachmentData['list'],
            'is_sample' => $isSample,
        ];
    }

    /**
     * Resolves recipient CRM record, email, and tracking token.
     */
    public function resolveRecipientContext(
        Campaign $campaign,
        RecipientLog|CampaignMember|array|null $recipient = null,
        ?string $overrideTrackingToken = null
    ): array {
        $recipientLogId = null;
        $isSample = false;
        $trackingToken = $overrideTrackingToken;
        $email = null;
        $sfRecord = null;

        if ($recipient instanceof RecipientLog) {
            $recipientLogId = $recipient->id;
            $email = $recipient->email;
            $trackingToken = $trackingToken ?: $recipient->tracking_token;

            $recordId = $recipient->salesforce_record_id;
            if ($recordId && !str_starts_with($recordId, 'RAW_') && $recordId !== 'N/A') {
                $sfRecord = $this->sfService->getSalesforceRecord($recordId);
            }
            if (!$sfRecord && $email) {
                $sfRecord = $this->sfService->getSalesforceRecordByEmail($email);
            }

            if (!$sfRecord) {
                $member = $recipient->campaignMember;
                $fullName = $member ? trim((string)$member->name) : '';
                $parts = $fullName ? preg_split('/\s+/', $fullName, 2) : ['', ''];
                $sfRecord = [
                    'id' => $recordId,
                    'first_name' => $parts[0] ?? '',
                    'last_name' => $parts[1] ?? '',
                    'name' => $fullName ?: $email,
                    'company' => $member ? trim((string)$member->company) : '',
                    'email' => $email,
                    'phone' => '',
                    'website' => '',
                    'owner_name' => $member ? trim((string)$member->owner_name) : '',
                    'object_type' => $recipient->salesforce_object ?: 'Contact',
                ];
            }
        } elseif ($recipient instanceof CampaignMember) {
            $email = $recipient->email;
            $recordId = $recipient->salesforce_record_id;
            if ($recordId && !str_starts_with($recordId, 'RAW_') && $recordId !== 'N/A') {
                $sfRecord = $this->sfService->getSalesforceRecord($recordId);
            }
            if (!$sfRecord && $email) {
                $sfRecord = $this->sfService->getSalesforceRecordByEmail($email);
            }

            if (!$sfRecord) {
                $fullName = trim((string)$recipient->name);
                $parts = $fullName ? preg_split('/\s+/', $fullName, 2) : ['', ''];
                $sfRecord = [
                    'id' => $recordId,
                    'first_name' => $parts[0] ?? '',
                    'last_name' => $parts[1] ?? '',
                    'name' => $fullName ?: $email,
                    'company' => trim((string)$recipient->company),
                    'email' => $email,
                    'phone' => '',
                    'website' => '',
                    'owner_name' => trim((string)$recipient->owner_name),
                    'object_type' => $recipient->record_type ?: 'Contact',
                ];
            }

            $log = RecipientLog::where('campaign_id', $campaign->id)
                ->where('campaign_member_id', $recipient->id)
                ->first();
            if ($log) {
                $recipientLogId = $log->id;
                $trackingToken = $trackingToken ?: $log->tracking_token;
            }
        } elseif (is_array($recipient)) {
            $sfRecord = $recipient;
            $email = $recipient['email'] ?? null;
            $trackingToken = $trackingToken ?: ($recipient['tracking_token'] ?? null);
            $recipientLogId = $recipient['recipient_log_id'] ?? ($recipient['id'] ?? null);
        } else {
            // No recipient explicitly provided: find the first available recipient in logs
            $log = RecipientLog::where('campaign_id', $campaign->id)
                ->whereIn('delivery_status', ['sent', 'delivered', 'opened', 'queued'])
                ->first();

            if (!$log) {
                $log = RecipientLog::where('campaign_id', $campaign->id)->first();
            }

            if ($log) {
                return $this->resolveRecipientContext($campaign, $log, $overrideTrackingToken);
            }

            // Check CampaignMember if logs are not yet populated
            $member = CampaignMember::where('campaign_id', $campaign->id)->first();
            if ($member) {
                return $this->resolveRecipientContext($campaign, $member, $overrideTrackingToken);
            }

            // Fallback: Clean representative sample recipient for newly created drafts
            $isSample = true;
            $email = 'recipient@example.com';
            $trackingToken = 'preview-token';
            $sfRecord = [
                'id' => 'PREVIEW_SAMPLE',
                'first_name' => 'Valued',
                'last_name' => 'Customer',
                'name' => 'Valued Customer',
                'company' => 'Acme Corporation',
                'email' => $email,
                'phone' => '+1 (555) 000-0000',
                'website' => 'https://example.com',
                'owner_name' => $campaign->user ? ($campaign->user->name ?: $campaign->user->username) : 'Salesforce Owner',
                'object_type' => 'Contact',
            ];
        }

        return [
            'sf_record' => $sfRecord,
            'email' => $email,
            'tracking_token' => $trackingToken,
            'recipient_log_id' => $recipientLogId,
            'is_sample' => $isSample,
        ];
    }

    /**
     * Resolves the configured sender signature block.
     */
    public function resolveSignatureHtml(Campaign $campaign, ?User $sender = null): ?string
    {
        // 1. Existing snapshot captured on campaign
        if (!empty($campaign->signature_snapshot)) {
            return $campaign->signature_snapshot;
        }

        // 2. Explicit signature ID configured on campaign
        if (!empty($campaign->signature_id)) {
            $sig = UserSignature::find($campaign->signature_id);
            if ($sig) {
                return $sig->renderHtml();
            }
        }

        // 3. If campaign is saved in DB and has no signature configured, do not attach any signature
        if ($campaign->exists) {
            return null;
        }

        // 4. For unsaved/in-memory models, check if sender has an explicit default signature
        if ($sender) {
            $defaultSig = $sender->signatures()->where('is_default', true)->first();
            if ($defaultSig) {
                return $defaultSig->renderHtml();
            }
        }

        return null;
    }

    /**
     * Builds the exact HTML download section for attachments.
     */
    public function buildAttachmentSection(Campaign $campaign): array
    {
        if (empty($campaign->attachments)) {
            return ['html' => '', 'count' => 0, 'list' => []];
        }

        $attachmentList = is_string($campaign->attachments)
            ? json_decode($campaign->attachments, true)
            : $campaign->attachments;

        if (!is_array($attachmentList) || empty($attachmentList)) {
            return ['html' => '', 'count' => 0, 'list' => []];
        }

        $attachmentLinks = [];
        $itemsList = [];

        foreach ($attachmentList as $filename) {
            $attachment = $this->attachmentService->getOrCreateForCampaignFile(
                $campaign,
                $filename,
                $campaign->user_id
            );

            $downloadUrl = $attachment->getDownloadUrl();
            $downloadUrl = preg_replace('/^http:\/\//i', 'https://', $downloadUrl);

            $safeDownloadUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');
            $safeFilename = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

            $fileSizeFormatted = $attachment->file_size > 0
                ? ' (' . htmlspecialchars($attachment->formatted_file_size, ENT_QUOTES, 'UTF-8') . ')'
                : '';

            $itemsList[] = [
                'filename' => $filename,
                'file_size' => $attachment->file_size,
                'formatted_size' => $attachment->formatted_file_size,
                'download_url' => $downloadUrl,
            ];

            $attachmentLinks[] =
                '<li style="margin:8px 0;padding:12px 16px;background:#ffffff;border:1px solid #cbd5e1;border-radius:6px;list-style:none;">'
                . '<div style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:4px;">'
                . 'File: <strong>' . $safeFilename . '</strong>' . $fileSizeFormatted
                . '</div>'
                . '<div style="font-size:12px;color:#475569;">'
                . '<a href="' . $safeDownloadUrl . '" '
                . 'style="color:#2563eb;text-decoration:underline;font-weight:600;" '
                . 'target="_blank" rel="noopener noreferrer">'
                . 'Click Here to Download'
                . '</a>'
                . '</div>'
                . '</li>';
        }

        $count = count($attachmentList);
        $attachmentHtml = '<div style="margin-top: 24px; padding: 16px 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">'
            . '<p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #1e293b;">📎 Download Your Attachment (' . $count . '):</p>'
            . '<ul style="margin: 0; padding: 0;">' . implode('', $attachmentLinks) . '</ul>'
            . '<p style="margin: 10px 0 0 0; font-size: 11px; color: #64748b;">Direct download link &bull; No login or account required.</p>'
            . '</div>';

        return [
            'html' => $attachmentHtml,
            'count' => $count,
            'list' => $itemsList,
        ];
    }

    /**
     * Builds the exact recipient-specific unsubscribe footer.
     */
    public function buildUnsubscribeFooter(?string $email, ?string $trackingToken = null): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        if (!empty($trackingToken) && $trackingToken !== 'preview-token') {
            $unsubUrl = "{$baseUrl}/track/unsubscribe/{$trackingToken}";
        } else {
            $unsubUrl = '#unsubscribe-preview';
        }

        $emailDisplay = !empty($email) ? e($email) : 'recipient@example.com';

        return '<p style="font-size: 11px; color: #64748b; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px;">This email was sent to ' . $emailDisplay . '. If you wish to unsubscribe, please <a href="' . $unsubUrl . '" style="color:#2563eb;text-decoration:underline;" target="_blank" rel="noopener noreferrer">click here</a>.</p>';
    }

    /**
     * Wrap the raw body HTML in a complete isolated HTML document for iframe presentation.
     */
    public function wrapInHtmlDocument(string $bodyHtml, string $subject = ''): string
    {
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeSubject}</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #1e293b;
            background-color: #ffffff;
            -webkit-font-smoothing: antialiased;
        }
        p {
            margin: 0 0 1em 0;
        }
        p:last-child {
            margin-bottom: 0;
        }
        a {
            color: #2563eb;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        table {
            border-collapse: collapse;
        }
    </style>
</head>
<body>
    {$bodyHtml}
</body>
</html>
HTML;
    }
}

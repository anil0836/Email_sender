<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\EmailAttachment;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    /**
     * Store an uploaded file in secure private storage and create an attachment record.
     */
    public function storeUploadedFile(
        UploadedFile $file,
        ?string $campaignId = null,
        ?int $userId = null,
        ?Carbon $expiresAt = null
    ): EmailAttachment {
        $originalFilename = $file->getClientOriginalName();
        $safeOriginalFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalFilename);

        // Generate cryptographically secure token
        $plainToken = bin2hex(random_bytes(32)); // 64 hex characters
        $tokenHash = hash('sha256', $plainToken);

        // Store under private email-attachments folder
        $subFolder = $campaignId ? "email-attachments/{$campaignId}" : "email-attachments/general";
        $uniqueFilename = Str::uuid()->toString() . '_' . $safeOriginalFilename;
        $storagePath = "{$subFolder}/{$uniqueFilename}";

        // Save file to private local disk (storage/app/private/email-attachments/...)
        $file->storeAs($subFolder, $uniqueFilename, 'local');

        $mimeType = $file->getClientMimeType() ?: $this->guessMimeType($originalFilename);
        $fileSize = $file->getSize() ?: 0;

        return EmailAttachment::create([
            'campaign_id' => $campaignId,
            'uploaded_by' => $userId,
            'original_filename' => $originalFilename,
            'storage_path' => $storagePath,
            'disk' => 'local',
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'token_hash' => $tokenHash,
            'token' => $plainToken,
            'expires_at' => $expiresAt,
            'download_count' => 0,
        ]);
    }

    /**
     * Get or create an EmailAttachment record for a campaign filename.
     * Ensures backward-compatibility for legacy campaigns or attachments submitted by name.
     */
    public function getOrCreateForCampaignFile(Campaign $campaign, string $filename, ?int $userId = null): EmailAttachment
    {
        $existing = EmailAttachment::where('campaign_id', $campaign->id)
            ->where('original_filename', $filename)
            ->first();

        if ($existing) {
            // Ensure token is populated if missing
            if (empty($existing->token)) {
                $plainToken = bin2hex(random_bytes(32));
                $existing->update([
                    'token' => $plainToken,
                    'token_hash' => hash('sha256', $plainToken),
                ]);
            }
            return $existing;
        }

        // Locate existing physical file across private storage folders
        $disk = Storage::disk('local');
        $possiblePaths = [
            "email-attachments/{$campaign->id}/{$filename}",
            "attachments/{$campaign->id}/{$filename}",
        ];

        $foundPath = null;
        foreach ($possiblePaths as $path) {
            if ($disk->exists($path)) {
                $foundPath = $path;
                break;
            }
        }

        // If not found in local, check public storage disk fallback
        if (!$foundPath && Storage::disk('public')->exists("attachments/{$campaign->id}/{$filename}")) {
            $foundPath = "attachments/{$campaign->id}/{$filename}";
            $disk = Storage::disk('public');
        }

        // Default path if not yet written
        if (!$foundPath) {
            $foundPath = "attachments/{$campaign->id}/{$filename}";
        }

        $fileSize = $disk->exists($foundPath) ? $disk->size($foundPath) : 0;
        $mimeType = $this->guessMimeType($filename);

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        return EmailAttachment::create([
            'campaign_id' => $campaign->id,
            'uploaded_by' => $userId ?: $campaign->user_id,
            'original_filename' => $filename,
            'storage_path' => $foundPath,
            'disk' => 'local',
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'token_hash' => $tokenHash,
            'token' => $plainToken,
            'download_count' => 0,
        ]);
    }

    /**
     * Find attachment by token (hashes token and queries token_hash, with plain token fallback).
     */
    public function findByToken(string $token): ?EmailAttachment
    {
        $hash = hash('sha256', $token);
        return EmailAttachment::where('token_hash', $hash)
            ->orWhere('token', $token)
            ->first();
    }

    /**
     * Generate the public download URL for an attachment.
     */
    public function generateDownloadUrl(EmailAttachment $attachment): string
    {
        return $attachment->getDownloadUrl();
    }

    /**
     * Revoke access to an attachment.
     */
    public function revokeAttachment(EmailAttachment $attachment): bool
    {
        return $attachment->revoke();
    }

    /**
     * Set attachment expiration date.
     */
    public function expireAttachment(EmailAttachment $attachment, ?Carbon $expiresAt = null): bool
    {
        $attachment->expires_at = $expiresAt ?: Carbon::now();
        return $attachment->save();
    }

    /**
     * Guess MIME type from filename extension as a reliable fallback.
     */
    public function guessMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
        };
    }
}

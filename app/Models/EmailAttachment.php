<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAttachment extends Model
{
    use HasFactory;

    protected $table = 'email_attachments';

    protected $fillable = [
        'campaign_id',
        'uploaded_by',
        'original_filename',
        'storage_path',
        'disk',
        'mime_type',
        'file_size',
        'token_hash',
        'token',
        'expires_at',
        'revoked_at',
        'download_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'download_count' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    /**
     * Relationship to associated Campaign.
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Relationship to User who uploaded the attachment.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Check if the attachment link has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the attachment has been manually revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check if attachment is currently available for public download.
     */
    public function isAvailable(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }

    /**
     * Revoke public download access.
     */
    public function revoke(): bool
    {
        $this->revoked_at = Carbon::now();
        return $this->save();
    }

    /**
     * Restore public download access.
     */
    public function restore(): bool
    {
        $this->revoked_at = null;
        return $this->save();
    }

    /**
     * Record a download event and increment the counter.
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => Carbon::now()]);
    }

    /**
     * Get the public download URL for this attachment.
     */
    public function getDownloadUrl(): string
    {
        $tokenToUse = $this->token ?: $this->token_hash;
        return route('attachments.public.download', ['token' => $tokenToUse]);
    }

    /**
     * Human-readable formatted file size attribute.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}

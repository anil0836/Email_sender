<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalSuppression extends Model
{
    use HasFactory;

    protected $table = 'global_suppression';

    protected $fillable = [
        'email',
        'normalized_email',
        'reason',
        'source',
        'status',
        'campaign_id',
        'provider_message_id',
        'external_event_id',
        'metadata',
        'added_at',
        'resubscribed_at',
        'resubscribed_by',
        'created_by',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'resubscribed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Always normalize email to lowercase and trimmed before saving.
     */
    public function setEmailAttribute($value): void
    {
        $clean = trim((string) $value);
        $this->attributes['email'] = $clean;
        $this->attributes['normalized_email'] = strtolower($clean);
    }

    /**
     * Scope: Only currently active suppressions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'suppressed');
    }

    /**
     * Scope: Filter by normalized email.
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('normalized_email', strtolower(trim($email)));
    }

    public function resubscribedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resubscribed_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Check if an email address is actively suppressed (case-insensitive).
     */
    public static function isSuppressed(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $normalized = strtolower(trim($email));

        return static::where('normalized_email', $normalized)
            ->where('status', 'suppressed')
            ->exists();
    }

    /**
     * Idempotently record an unsubscription or suppression.
     */
    public static function recordUnsubscribe(
        string $email,
        string $reason = 'Unsubscribed via Pabbly Webhook',
        string $source = 'pabbly_webhook',
        ?string $campaignId = null,
        ?string $providerMessageId = null,
        ?array $metadata = null,
        ?string $externalEventId = null,
        ?int $userId = null
    ): self {
        $normalizedEmail = strtolower(trim($email));

        return static::updateOrCreate(
            ['normalized_email' => $normalizedEmail],
            [
                'email' => trim($email),
                'reason' => $reason,
                'source' => $source,
                'status' => 'suppressed',
                'campaign_id' => $campaignId,
                'provider_message_id' => $providerMessageId,
                'external_event_id' => $externalEventId,
                'metadata' => $metadata,
                'added_at' => now(),
                'resubscribed_at' => null,
                'resubscribed_by' => null,
                'created_by' => $userId,
            ]
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalSuppression extends Model
{
    use HasFactory;

    protected $table = 'global_suppression';

    protected $fillable = [
        'email',
        'reason',
        'source',
        'campaign_id',
        'provider_message_id',
        'metadata',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Always normalize email to lowercase and trimmed before saving.
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    /**
     * Check if an email address is globally suppressed (case-insensitive).
     */
    public static function isSuppressed(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        return static::where('email', strtolower(trim($email)))->exists();
    }

    /**
     * Idempotently record an unsubscription.
     */
    public static function recordUnsubscribe(
        string $email,
        string $reason = 'Unsubscribed via Pabbly Webhook',
        string $source = 'pabbly_webhook',
        ?string $campaignId = null,
        ?string $providerMessageId = null,
        ?array $metadata = null
    ): self {
        $normalizedEmail = strtolower(trim($email));

        return static::updateOrCreate(
            ['email' => $normalizedEmail],
            [
                'reason' => $reason,
                'source' => $source,
                'campaign_id' => $campaignId,
                'provider_message_id' => $providerMessageId,
                'metadata' => $metadata,
                'added_at' => now(),
            ]
        );
    }
}

<?php

namespace App\Services;

use App\Models\GlobalSuppression;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailSuppressionService
{
    /**
     * Normalize an email address uniformly across the entire platform.
     */
    public function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Check whether an email is currently actively suppressed.
     */
    public function isSuppressed(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        return GlobalSuppression::isSuppressed($email);
    }

    /**
     * Batch check emails for suppression (single indexed database query).
     * Returns an array of lowercase suppressed email strings.
     */
    public function getSuppressedInBatch(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        $normalizedList = array_values(array_unique(array_filter(array_map(
            fn($e) => $this->normalizeEmail($e),
            $emails
        ))));

        if (empty($normalizedList)) {
            return [];
        }

        return GlobalSuppression::query()
            ->whereIn('normalized_email', $normalizedList)
            ->where('status', 'suppressed')
            ->pluck('normalized_email')
            ->toArray();
    }

    /**
     * Filter a Laravel Collection of objects or arrays having an email property.
     */
    public function filterSuppressed(Collection $recipients, string $emailKey = 'email'): Collection
    {
        $emails = $recipients->pluck($emailKey)->filter()->toArray();
        $suppressedSet = array_flip($this->getSuppressedInBatch($emails));

        return $recipients->filter(function ($item) use ($emailKey, $suppressedSet) {
            $rawEmail = is_array($item) ? ($item[$emailKey] ?? null) : ($item->{$emailKey} ?? null);
            $normalized = $this->normalizeEmail($rawEmail);
            return !isset($suppressedSet[$normalized]);
        });
    }

    /**
     * Suppress an email address idempotently.
     */
    public function suppress(
        string $email,
        string $reason = 'Unsubscribed via Pabbly Webhook',
        string $source = 'pabbly_webhook',
        ?string $campaignId = null,
        ?string $messageId = null,
        ?string $externalEventId = null,
        ?array $metadata = null,
        ?int $userId = null
    ): GlobalSuppression {
        $normalized = $this->normalizeEmail($email);

        return DB::transaction(function () use (
            $email, $normalized, $reason, $source, $campaignId, $messageId, $externalEventId, $metadata, $userId
        ) {
            $record = GlobalSuppression::updateOrCreate(
                ['normalized_email' => $normalized],
                [
                    'email' => trim($email),
                    'reason' => $reason,
                    'source' => $source,
                    'status' => 'suppressed',
                    'campaign_id' => $campaignId,
                    'provider_message_id' => $messageId,
                    'external_event_id' => $externalEventId,
                    'metadata' => $metadata,
                    'added_at' => Carbon::now(),
                    'resubscribed_at' => null,
                    'resubscribed_by' => null,
                    'created_by' => $userId,
                ]
            );

            Log::info("[EmailSuppressionService] Suppressed email: {$normalized}", [
                'reason' => $reason,
                'source' => $source,
                'campaign_id' => $campaignId,
            ]);

            return $record;
        });
    }

    /**
     * Explicitly resubscribe an email address with audit tracking.
     */
    public function resubscribe(string $email, int $authorizedUserId, string $remark = 'Manual admin authorization'): bool
    {
        $normalized = $this->normalizeEmail($email);

        $record = GlobalSuppression::where('normalized_email', $normalized)->first();
        if (!$record) {
            return false;
        }

        $record->update([
            'status' => 'resubscribed',
            'resubscribed_at' => Carbon::now(),
            'resubscribed_by' => $authorizedUserId,
            'metadata' => array_merge($record->metadata ?? [], [
                'resubscribe_audit' => [
                    'authorized_by_user_id' => $authorizedUserId,
                    'timestamp' => Carbon::now()->toIso8601String(),
                    'remark' => $remark,
                ]
            ]),
        ]);

        Log::warning("[EmailSuppressionService] Email resubscribed: {$normalized} by user ID {$authorizedUserId}");

        return true;
    }

    /**
     * Compute suppression statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_active' => GlobalSuppression::where('status', 'suppressed')->count(),
            'unsubscribed' => GlobalSuppression::where('status', 'suppressed')
                ->where(function ($q) {
                    $q->where('reason', 'like', '%unsub%')
                      ->orWhere('source', 'like', '%pabbly%');
                })->count(),
            'hard_bounces' => GlobalSuppression::where('status', 'suppressed')
                ->where('reason', 'like', '%bounce%')->count(),
            'complaints' => GlobalSuppression::where('status', 'suppressed')
                ->where(function ($q) {
                    $q->where('reason', 'like', '%complaint%')
                      ->orWhere('reason', 'like', '%spam%');
                })->count(),
            'manual_suppressions' => GlobalSuppression::where('status', 'suppressed')
                ->whereIn('source', ['admin_manual', 'manual', 'csv_import'])->count(),
            'resubscribed' => GlobalSuppression::where('status', 'resubscribed')->count(),
        ];
    }
}

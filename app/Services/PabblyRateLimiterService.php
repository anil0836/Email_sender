<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PabblyRateLimiterService
{
    /**
     * Generate a unique cache identifier for a given Pabbly API key.
     */
    public function getAccountKey(?string $apiKey = null): string
    {
        $key = !empty($apiKey) ? trim($apiKey) : (string) config('pabbly.api_key', 'default');
        return 'pabbly_' . substr(hash('sha256', $key), 0, 16);
    }

    /**
     * Check if Pabbly rate limiting is globally enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('pabbly.rate_limit_enabled', true);
    }

    /**
     * Get the configured minimum interval in seconds between dispatch requests.
     */
    public function getIntervalSeconds(): int
    {
        return max(0, (int) config('pabbly.dispatch_interval_seconds', 5));
    }

    /**
     * Determine if the Pabbly account is currently in a temporary rate-limit cooldown.
     */
    public function isInCooldown(?string $apiKey = null): bool
    {
        $accountKey = $this->getAccountKey($apiKey);
        $cooldownUntil = Cache::get("pabbly:cooldown:{$accountKey}");

        if (!$cooldownUntil) {
            return false;
        }

        return Carbon::createFromTimestamp($cooldownUntil)->isFuture();
    }

    /**
     * Get remaining cooldown seconds for the Pabbly account.
     */
    public function getCooldownRemainingSeconds(?string $apiKey = null): int
    {
        $accountKey = $this->getAccountKey($apiKey);
        $cooldownUntil = Cache::get("pabbly:cooldown:{$accountKey}");

        if (!$cooldownUntil) {
            return 0;
        }

        return max(0, (int) ($cooldownUntil - time()));
    }

    /**
     * Place the Pabbly account into temporary rate-limit cooldown.
     */
    public function setCooldown(?string $apiKey = null, int $seconds = 60, string $reason = ''): void
    {
        $accountKey = $this->getAccountKey($apiKey);
        $seconds = max(1, $seconds);
        $until = time() + $seconds;

        Cache::put("pabbly:cooldown:{$accountKey}", $until, $seconds);

        Log::warning(sprintf(
            "[PabblyRateLimiter] Account %s placed in rate-limit cooldown for %ds (until %s).%s",
            $accountKey,
            $seconds,
            date('Y-m-d H:i:s', $until),
            $reason ? " Reason: {$reason}" : ''
        ));
    }

    /**
     * Clear cooldown and dispatch reservation state (primarily for tests or manual administrative resets).
     */
    public function clearState(?string $apiKey = null): void
    {
        $accountKey = $this->getAccountKey($apiKey);
        Cache::forget("pabbly:cooldown:{$accountKey}");
        Cache::forget("pabbly:next_slot:{$accountKey}");
        Cache::forget("pabbly:last_dispatch:{$accountKey}");
    }

    /**
     * Reserve and coordinate a dispatch slot across concurrent workers.
     * Ensures all requests sharing this Pabbly account observe the configured dispatch interval.
     *
     * @param string|null $apiKey
     * @param int|null $customInterval
     * @param int $maxWaitSeconds Maximum seconds worker will sleep before deferring
     * @return bool True if slot acquired and ready to send, False if deferred (e.g. cooldown or wait too long)
     */
    public function awaitDispatchSlot(?string $apiKey = null, ?int $customInterval = null, int $maxWaitSeconds = 15): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ($this->isInCooldown($apiKey)) {
            $rem = $this->getCooldownRemainingSeconds($apiKey);
            Log::warning("[PabblyRateLimiter] Dispatch skipped: Account is in rate-limit cooldown for {$rem}s.");
            return false;
        }

        $interval = $customInterval !== null ? max(0, $customInterval) : $this->getIntervalSeconds();
        if ($interval <= 0) {
            return true;
        }

        $accountKey = $this->getAccountKey($apiKey);
        $waitRequired = 0.0;
        $lockAcquired = false;

        try {
            $lock = Cache::lock("pabbly:slot_lock:{$accountKey}", 10);
            $lockAcquired = $lock->block(10, function () use ($accountKey, $interval, $maxWaitSeconds, &$waitRequired, $apiKey) {
                if ($this->isInCooldown($apiKey)) {
                    $waitRequired = -1.0;
                    return true;
                }

                $now = microtime(true);
                $nextSlot = (float) Cache::get("pabbly:next_slot:{$accountKey}", 0.0);

                if ($nextSlot <= $now) {
                    $targetSlot = $now;
                    $newNextSlot = $now + $interval;
                } else {
                    $targetSlot = $nextSlot;
                    $newNextSlot = $nextSlot + $interval;
                }

                $diff = $targetSlot - $now;
                if ($diff > $maxWaitSeconds) {
                    // Queue congestion: slot is too far in future for synchronous wait
                    $waitRequired = $diff;
                    return true;
                }

                $waitRequired = max(0.0, $diff);
                Cache::put("pabbly:next_slot:{$accountKey}", $newNextSlot, now()->addMinutes(15));
                return true;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning("[PabblyRateLimiter] Lock timeout while reserving slot for {$accountKey}.");
            return false;
        } catch (\Throwable $e) {
            Log::error("[PabblyRateLimiter] Error reserving slot lock for {$accountKey}: " . $e->getMessage());
            return false;
        }

        if (!$lockAcquired) {
            Log::warning("[PabblyRateLimiter] Could not acquire slot reservation lock for {$accountKey}.");
            return false;
        }

        if ($waitRequired < 0.0) {
            return false; // In cooldown
        }

        if ($waitRequired > $maxWaitSeconds) {
            Log::info(sprintf(
                "[PabblyRateLimiter] Dispatch slot for %s is in %.2fs (exceeds max synchronous wait of %ds). Deferring to next cycle.",
                $accountKey,
                $waitRequired,
                $maxWaitSeconds
            ));
            return false;
        }

        if ($waitRequired > 0.01) {
            Log::info(sprintf(
                "[PabblyRateLimiter] Pacing dispatch for %s: waiting %.2fs to maintain %ds interval.",
                $accountKey,
                $waitRequired,
                $interval
            ));
            usleep((int) round($waitRequired * 1_000_000));
        }

        return true;
    }

    /**
     * Record dispatch timestamp for auditing and rate-limiting metrics.
     */
    public function recordDispatch(?string $apiKey = null): void
    {
        $accountKey = $this->getAccountKey($apiKey);
        Cache::put("pabbly:last_dispatch:{$accountKey}", microtime(true), now()->addMinutes(15));
    }

    /**
     * Calculate exponential backoff delay in seconds with bounded jitter.
     */
    public function calculateBackoffDelay(int $retryAttempt, ?int $retryAfterHeader = null): int
    {
        $maxDelay = (int) config('pabbly.retry_max_delay_seconds', 900);

        if ($retryAfterHeader !== null && $retryAfterHeader > 0) {
            return min($maxDelay, $retryAfterHeader);
        }

        $baseDelay = (int) config('pabbly.retry_base_delay_seconds', 60);
        $attempt = max(1, $retryAttempt);

        // Exponential backoff: base * 2^(attempt - 1)
        $delay = (int) ($baseDelay * pow(2, $attempt - 1));
        $delay = min($maxDelay, $delay);

        // Add 0-10% jitter (up to 15 seconds)
        $maxJitter = min(15, (int) round($delay * 0.1));
        $jitter = $maxJitter > 0 ? rand(0, $maxJitter) : 0;

        return min($maxDelay, $delay + $jitter);
    }
}

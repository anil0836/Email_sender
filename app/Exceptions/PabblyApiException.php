<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PabblyApiException extends RuntimeException
{
    public function __construct(
        string $message = '',
        protected int $statusCode = 0,
        protected bool $isRetryable = false,
        protected ?string $pabblyCampaignId = null,
        protected ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isRetryable(): bool
    {
        return $this->isRetryable;
    }

    public function isRateLimit(): bool
    {
        return $this->statusCode === 429;
    }

    public function getPabblyCampaignId(): ?string
    {
        return $this->pabblyCampaignId;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}

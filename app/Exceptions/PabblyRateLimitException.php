<?php

namespace App\Exceptions;

use Throwable;

class PabblyRateLimitException extends PabblyApiException
{
    public function __construct(
        string $message = 'Too many requests from this IP or user, please try again later.',
        int $statusCode = 429,
        ?int $retryAfter = null,
        ?string $pabblyCampaignId = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: $statusCode,
            isRetryable: true,
            pabblyCampaignId: $pabblyCampaignId,
            retryAfter: $retryAfter,
            previous: $previous
        );
    }
}

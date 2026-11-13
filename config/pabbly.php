<?php

return [
    'api_key' => env('PABBLY_API_KEY', 'pem_84ebe548d2b09876a8db0c7c07565f9a'),
    'base_url' => (function () {
        $url = trim((string) env('PABBLY_API_BASE_URL', 'https://emails.pabbly.com/api/v2'));
        $url = rtrim($url, ';/ ');
        return preg_replace('#/campaigns(/send-to-individual)?/?$#i', '', $url) ?: 'https://emails.pabbly.com/api/v2';
    })(),
    'webhook_token' => env('PABBLY_WEBHOOK_TOKEN', '465391a5d62179d5fe916746c44bacc8bdaf7e7e1bf8b015532e8985f0246327'),
    'delivery_server_id' => env('PABBLY_DELIVERY_SERVER_ID', 'send-with-us'),
    'sending_domain' => env('PABBLY_SENDING_DOMAIN', 'mailer-b2bexportsllc.com'),
    'from_email' => env('PABBLY_FROM_EMAIL', env('PABBLY_SENDER_EMAIL', 'contact@mailer-b2bexportsllc.com')),
    'from_name' => env('PABBLY_FROM_NAME', 'anil patel'),
    'to_email' => env('PABBLY_TO_EMAIL', 'arpit@retrotech.in'),
    'to_name' => env('PABBLY_TO_NAME', 'Recipient name'),
    'reply_to' => env('PABBLY_REPLY_TO', 'support@b2bexportsllc.com'),

    /*
    |--------------------------------------------------------------------------
    | Pabbly API Rate Limiting & Retry Configuration
    |--------------------------------------------------------------------------
    |
    | dispatch_interval_seconds: Minimum seconds between Pabbly dispatch requests (shared across campaigns/workers).
    | rate_limit_enabled: Enable/disable dispatch rate limiting.
    | max_retry_attempts: Maximum retry attempts for temporary rate limit (429) errors.
    | retry_base_delay_seconds: Base delay in seconds for exponential backoff.
    | retry_max_delay_seconds: Maximum delay in seconds for rate limit retries.
    |
    */
    'dispatch_interval_seconds' => (int) env('PABBLY_DISPATCH_INTERVAL_SECONDS', 5),
    'rate_limit_enabled' => filter_var(env('PABBLY_RATE_LIMIT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'max_retry_attempts' => (int) env('PABBLY_MAX_RETRY_ATTEMPTS', 5),
    'retry_base_delay_seconds' => (int) env('PABBLY_RETRY_BASE_DELAY_SECONDS', 60),
    'retry_max_delay_seconds' => (int) env('PABBLY_RETRY_MAX_DELAY_SECONDS', 900),
];

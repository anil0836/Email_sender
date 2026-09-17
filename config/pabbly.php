<?php

return [
    'api_key' => env('PABBLY_API_KEY', 'pem_84ebe548d2b09876a8db0c7c07565f9a'),
    'base_url' => rtrim(env('PABBLY_API_BASE_URL', 'https://emails.pabbly.com/api/v2'), '/'),
    'webhook_token' => env('PABBLY_WEBHOOK_TOKEN', '465391a5d62179d5fe916746c44bacc8bdaf7e7e1bf8b015532e8985f0246327'),
    'delivery_server_id' => env('PABBLY_DELIVERY_SERVER_ID', 'send-with-us'),
    'from_email' => env('PABBLY_FROM_EMAIL', 'rma@proitbuyer.com'),
    'from_name' => env('PABBLY_FROM_NAME', 'anil patel'),
    'to_email' => env('PABBLY_TO_EMAIL', 'arpit@retrotech.in'),
    'to_name' => env('PABBLY_TO_NAME', 'Recipient name'),
];

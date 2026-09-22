<?php

namespace App\Http\Controllers;

use App\Services\PabblyWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PabblyWebhookController extends Controller
{
    public function __construct(
        protected PabblyWebhookService $webhookService
    ) {}

    /**
     * Handle incoming Pabbly Webhook requests.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // 1. Verify Webhook Secret Token
        $configuredToken = (string) config('pabbly.webhook_token');
        $incomingToken = (string) (
            $request->query('token') 
            ?: $request->header('X-Pabbly-Token') 
            ?: $request->bearerToken()
        );

        if (!empty($configuredToken) && !hash_equals($configuredToken, $incomingToken)) {
            Log::warning('[PABBLY WEBHOOK] Unauthorized webhook attempt: Token mismatch.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Unauthorized token.'], 401);
        }

        // 2. Safe Payload Logging for Testing / Debugging
        if (config('app.debug') || env('PABBLY_LOG_PAYLOADS', false)) {
            Log::debug('[PABBLY WEBHOOK] Raw Payload:', $request->all());
        }

        // 3. Process via Service
        try {
            $result = $this->webhookService->processPayload($request->all());

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error('[PABBLY WEBHOOK] Error processing webhook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook processing failed.'], 500);
        }
    }
}

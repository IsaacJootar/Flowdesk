<?php

namespace App\Http\Controllers;

use App\Services\Execution\SubscriptionBillingWebhookReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles incoming webhooks from execution/billing providers.
 * Validates webhook signatures and delegates processing to the reconciliation service.
 */
class ExecutionWebhookController extends Controller
{
    /**
     * Process an incoming webhook from a provider.
     *
     * @param Request $request The incoming HTTP request
     * @param string $provider The provider name (e.g., 'stripe', 'custom')
     * @param SubscriptionBillingWebhookReconciliationService $service Handles webhook processing
     * @return JsonResponse Webhook processing result with event ID for idempotency
     */
    public function __invoke(Request $request, string $provider, SubscriptionBillingWebhookReconciliationService $service): JsonResponse
    {
        // Normalize all request headers into a consistent string format
        // Handles both single-value and array-based header values
        /** @var array<string,string> $headers */
        $headers = collect($request->headers->all())
            ->mapWithKeys(static function ($value, $key): array {
                $normalized = is_array($value) ? implode(',', $value) : (string) $value;

                return [(string) $key => $normalized];
            })
            ->all();

        // Extract the webhook signature from request headers
        // Tries multiple common signature header names for provider compatibility
        $signature = (string) (
            $request->header('X-Execution-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? ''
        );

        // Delegate to the reconciliation service for signature validation and processing
        $result = $service->receive(
            provider: $provider,
            headers: $headers,
            body: (string) $request->getContent(),
            signature: $signature !== '' ? $signature : null,
        );

        // Return standardized JSON response with status code
        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'event_id' => $result['event_id'],
        ], $result['status']);
    }
}

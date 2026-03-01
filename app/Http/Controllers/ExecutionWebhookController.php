<?php

namespace App\Http\Controllers;

use App\Services\Execution\SubscriptionBillingWebhookReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, SubscriptionBillingWebhookReconciliationService $service): JsonResponse
    {
        /** @var array<string,string> $headers */
        $headers = collect($request->headers->all())
            ->mapWithKeys(static function ($value, $key): array {
                $normalized = is_array($value) ? implode(',', $value) : (string) $value;

                return [(string) $key => $normalized];
            })
            ->all();

        $signature = (string) (
            $request->header('X-Execution-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? ''
        );

        $result = $service->receive(
            provider: $provider,
            headers: $headers,
            body: (string) $request->getContent(),
            signature: $signature !== '' ? $signature : null,
        );

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'event_id' => $result['event_id'],
        ], $result['status']);
    }
}
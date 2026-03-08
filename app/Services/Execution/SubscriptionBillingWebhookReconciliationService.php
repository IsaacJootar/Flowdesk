<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Treasury\SyncWebhookExecutionExceptionService;
use Carbon\CarbonImmutable;

/**
 * SubscriptionBillingWebhookReconciliationService
 *
 * Handles reconciliation of subscription billing webhooks from external payment providers.
 * Processes webhook events, verifies signatures, resolves billing attempts, and updates
 * billing statuses based on provider event types.
 *
 * Key responsibilities:
 * - Webhook signature verification and payload normalization
 * - Duplicate event detection and handling
 * - Billing attempt resolution from webhook payload
 * - Status mapping from provider event types to internal billing statuses
 * - Fallback to payout reconciliation if billing attempt not found
 * - Exception synchronization for monitoring and debugging
 */
class SubscriptionBillingWebhookReconciliationService
{
    public function __construct(
        private readonly TenantExecutionAdapterFactory $adapterFactory,
        private readonly SubscriptionBillingAttemptProcessor $attemptProcessor,
        private readonly RequestPayoutWebhookReconciliationService $requestPayoutWebhookReconciliationService,
        private readonly SyncWebhookExecutionExceptionService $syncWebhookExecutionExceptionService,
    ) {
    }

    /**
     * Receive and process a billing webhook from a payment provider
     *
     * Handles the complete webhook reconciliation flow:
     * 1. Creates webhook event record
     * 2. Verifies webhook signature and normalizes payload
     * 3. Checks for duplicate events
     * 4. Resolves associated billing attempt
     * 5. Maps event type to billing status
     * 6. Updates attempt status via processor
     * 7. Falls back to payout reconciliation if no billing attempt found
     *
     * @param  array<string,string>  $headers HTTP headers from the webhook request
     * @return array{status:int,ok:bool,message:string,event_id:int} Processing result with status code and event ID
     */
    public function receive(string $provider, array $headers, string $body, ?string $signature = null): array
    {
        $providerKey = strtolower(trim($provider));

        // Create initial webhook event record
        $event = ExecutionWebhookEvent::query()->create([
            'provider_key' => $providerKey,
            'verification_status' => 'pending',
            'processing_status' => 'queued',
            'received_at' => now(),
            'signature' => $signature,
            'headers' => $headers,
            'payload' => $this->decodedPayload($body),
        ]);

        // Verify webhook signature and normalize payload
        $verifier = $this->adapterFactory->webhookVerifierForProvider($providerKey);
        $verification = $verifier->verify(new ProviderWebhookPayloadData(
            provider: $providerKey,
            body: $body,
            headers: $headers,
            signature: $signature,
            receivedAt: CarbonImmutable::now(),
        ));

        // Update event with verification results
        $event->forceFill([
            'external_event_id' => $verification->eventId,
            'event_type' => $verification->eventType,
            'occurred_at' => $verification->occurredAt?->toDateTimeString(),
            'normalized_payload' => $verification->normalizedPayload,
            'verification_status' => $verification->valid ? 'valid' : 'invalid',
        ])->save();

        // Handle invalid webhook verification
        if (! $verification->valid) {
            $event->forceFill([
                'processing_status' => 'failed',
                'failure_reason' => $verification->reason ?: 'Webhook verification failed.',
                'processed_at' => now(),
            ])->save();
            $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'billing_webhook');

            return [
                'status' => 422,
                'ok' => false,
                'message' => (string) ($verification->reason ?: 'Invalid webhook signature.'),
                'event_id' => (int) $event->id,
            ];
        }

        // Check for duplicate processed events
        if ($this->isDuplicateProcessedEvent($event)) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'Duplicate provider event already processed.',
                'processed_at' => now(),
            ])->save();
            $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'billing_webhook');

            return [
                'status' => 202,
                'ok' => true,
                'message' => 'Duplicate event ignored.',
                'event_id' => (int) $event->id,
            ];
        }

        // Resolve the associated billing attempt from webhook payload
        $attempt = $this->resolveAttempt($verification->normalizedPayload);
        if (! $attempt) {
            // Fallback to payout reconciliation if no billing attempt found
            $result = $this->requestPayoutWebhookReconciliationService->reconcile(
                event: $event,
                eventType: $verification->eventType,
                eventId: $verification->eventId,
                normalizedPayload: $verification->normalizedPayload,
            );

            $this->syncWebhookExecutionExceptionService->syncForEvent($event->fresh() ?? $event, 'billing_webhook_fallback');

            return $result;
        }

        // Link event to billing attempt
        $event->forceFill([
            'company_id' => (int) $attempt->company_id,
            'tenant_subscription_id' => (int) $attempt->tenant_subscription_id,
            'tenant_subscription_billing_attempt_id' => (int) $attempt->id,
        ])->save();

        // Map event type to billing status
        $nextStatus = $this->statusFromEventType((string) ($verification->eventType ?? ''));
        if ($nextStatus === null) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'Event type is not mapped to billing lifecycle.',
                'processed_at' => now(),
            ])->save();
            $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'billing_webhook');

            return [
                'status' => 202,
                'ok' => true,
                'message' => 'Webhook accepted but event type is not mapped.',
                'event_id' => (int) $event->id,
            ];
        }

        // Update billing attempt status based on webhook event
        $this->attemptProcessor->markFromWebhook(
            attempt: $attempt,
            nextStatus: $nextStatus,
            eventId: $verification->eventId,
            normalizedPayload: $verification->normalizedPayload,
        );

        // Mark event as successfully processed
        $event->forceFill([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'failure_reason' => null,
        ])->save();
        $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'billing_webhook');

        return [
            'status' => 202,
            'ok' => true,
            'message' => 'Webhook accepted and reconciled.',
            'event_id' => (int) $event->id,
        ];
    }

    /**
     * Resolve billing attempt from webhook payload using multiple lookup strategies
     *
     * Attempts to find the billing attempt using:
     * 1. Direct billing_attempt_id
     * 2. Idempotency key
     * 3. External invoice ID
     * 4. Provider reference
     *
     * @param  array<string,mixed>  $payload Normalized webhook payload
     */
    private function resolveAttempt(array $payload): ?TenantSubscriptionBillingAttempt
    {
        // Try direct billing attempt ID lookup
        $attemptId = isset($payload['billing_attempt_id']) ? (int) $payload['billing_attempt_id'] : 0;
        if ($attemptId > 0) {
            return TenantSubscriptionBillingAttempt::query()->find($attemptId);
        }

        // Try idempotency key lookup
        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            return TenantSubscriptionBillingAttempt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }

        // Try external invoice ID lookup
        $invoiceId = trim((string) ($payload['external_invoice_id'] ?? ''));
        if ($invoiceId !== '') {
            return TenantSubscriptionBillingAttempt::query()
                ->where('external_invoice_id', $invoiceId)
                ->first();
        }

        // Try provider reference lookup
        $providerReference = trim((string) ($payload['provider_reference'] ?? ''));
        if ($providerReference !== '') {
            return TenantSubscriptionBillingAttempt::query()
                ->where('provider_reference', $providerReference)
                ->first();
        }

        return null;
    }

    /**
     * Check if this is a duplicate of an already processed event
     *
     * Prevents double-processing of the same provider event by checking
     * for existing valid, processed events with the same external event ID.
     */
    private function isDuplicateProcessedEvent(ExecutionWebhookEvent $event): bool
    {
        $eventId = trim((string) ($event->external_event_id ?? ''));
        if ($eventId === '') {
            return false;
        }

        return ExecutionWebhookEvent::query()
            ->where('provider_key', (string) $event->provider_key)
            ->where('external_event_id', $eventId)
            ->where('verification_status', 'valid')
            ->where('processing_status', 'processed')
            ->where('id', '!=', (int) $event->id)
            ->exists();
    }

    /**
     * Map provider event type to internal billing status
     *
     * Translates provider-specific event types to standardized billing statuses:
     * - Success events (settled, succeeded, paid) -> 'settled'
     * - Failure events (failed, declined) -> 'failed'
     * - Reversal events (reversed, refund, chargeback) -> 'reversed'
     */
    private function statusFromEventType(string $eventType): ?string
    {
        $normalized = strtolower(trim($eventType));

        if ($normalized === '') {
            return null;
        }

        // Success/payment completion events
        if (str_contains($normalized, 'settled') || str_contains($normalized, 'succeeded') || str_contains($normalized, 'paid')) {
            return 'settled';
        }

        // Failure/decline events
        if (str_contains($normalized, 'failed') || str_contains($normalized, 'declined')) {
            return 'failed';
        }

        // Reversal/refund events
        if (str_contains($normalized, 'reversed') || str_contains($normalized, 'refund') || str_contains($normalized, 'chargeback')) {
            return 'reversed';
        }

        return null;
    }

    /**
     * Decode JSON webhook payload
     *
     * @return array<string,mixed>|null Decoded payload array or null if invalid JSON
     */
    private function decodedPayload(string $body): ?array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}

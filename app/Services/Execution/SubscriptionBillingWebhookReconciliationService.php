<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use Carbon\CarbonImmutable;

class SubscriptionBillingWebhookReconciliationService
{
    public function __construct(
        private readonly TenantExecutionAdapterFactory $adapterFactory,
        private readonly SubscriptionBillingAttemptProcessor $attemptProcessor,
        private readonly RequestPayoutWebhookReconciliationService $requestPayoutWebhookReconciliationService,
    ) {
    }

    /**
     * @param  array<string,string>  $headers
     * @return array{status:int,ok:bool,message:string,event_id:int}
     */
    public function receive(string $provider, array $headers, string $body, ?string $signature = null): array
    {
        $providerKey = strtolower(trim($provider));

        $event = ExecutionWebhookEvent::query()->create([
            'provider_key' => $providerKey,
            'verification_status' => 'pending',
            'processing_status' => 'queued',
            'received_at' => now(),
            'signature' => $signature,
            'headers' => $headers,
            'payload' => $this->decodedPayload($body),
        ]);

        $verifier = $this->adapterFactory->webhookVerifierForProvider($providerKey);
        $verification = $verifier->verify(new ProviderWebhookPayloadData(
            provider: $providerKey,
            body: $body,
            headers: $headers,
            signature: $signature,
            receivedAt: CarbonImmutable::now(),
        ));

        $event->forceFill([
            'external_event_id' => $verification->eventId,
            'event_type' => $verification->eventType,
            'occurred_at' => $verification->occurredAt?->toDateTimeString(),
            'normalized_payload' => $verification->normalizedPayload,
            'verification_status' => $verification->valid ? 'valid' : 'invalid',
        ])->save();

        if (! $verification->valid) {
            $event->forceFill([
                'processing_status' => 'failed',
                'failure_reason' => $verification->reason ?: 'Webhook verification failed.',
                'processed_at' => now(),
            ])->save();

            return [
                'status' => 422,
                'ok' => false,
                'message' => (string) ($verification->reason ?: 'Invalid webhook signature.'),
                'event_id' => (int) $event->id,
            ];
        }

        if ($this->isDuplicateProcessedEvent($event)) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'Duplicate provider event already processed.',
                'processed_at' => now(),
            ])->save();

            return [
                'status' => 202,
                'ok' => true,
                'message' => 'Duplicate event ignored.',
                'event_id' => (int) $event->id,
            ];
        }

        $attempt = $this->resolveAttempt($verification->normalizedPayload);
        if (! $attempt) {
            // One webhook endpoint serves both billing and payout events.
            return $this->requestPayoutWebhookReconciliationService->reconcile(
                event: $event,
                eventType: $verification->eventType,
                eventId: $verification->eventId,
                normalizedPayload: $verification->normalizedPayload,
            );
        }

        $event->forceFill([
            'company_id' => (int) $attempt->company_id,
            'tenant_subscription_id' => (int) $attempt->tenant_subscription_id,
            'tenant_subscription_billing_attempt_id' => (int) $attempt->id,
        ])->save();

        $nextStatus = $this->statusFromEventType((string) ($verification->eventType ?? ''));
        if ($nextStatus === null) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'Event type is not mapped to billing lifecycle.',
                'processed_at' => now(),
            ])->save();

            return [
                'status' => 202,
                'ok' => true,
                'message' => 'Webhook accepted but event type is not mapped.',
                'event_id' => (int) $event->id,
            ];
        }

        $this->attemptProcessor->markFromWebhook(
            attempt: $attempt,
            nextStatus: $nextStatus,
            eventId: $verification->eventId,
            normalizedPayload: $verification->normalizedPayload,
        );

        $event->forceFill([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'failure_reason' => null,
        ])->save();

        return [
            'status' => 202,
            'ok' => true,
            'message' => 'Webhook accepted and reconciled.',
            'event_id' => (int) $event->id,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveAttempt(array $payload): ?TenantSubscriptionBillingAttempt
    {
        $attemptId = isset($payload['billing_attempt_id']) ? (int) $payload['billing_attempt_id'] : 0;
        if ($attemptId > 0) {
            return TenantSubscriptionBillingAttempt::query()->find($attemptId);
        }

        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            return TenantSubscriptionBillingAttempt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }

        $invoiceId = trim((string) ($payload['external_invoice_id'] ?? ''));
        if ($invoiceId !== '') {
            return TenantSubscriptionBillingAttempt::query()
                ->where('external_invoice_id', $invoiceId)
                ->first();
        }

        $providerReference = trim((string) ($payload['provider_reference'] ?? ''));
        if ($providerReference !== '') {
            return TenantSubscriptionBillingAttempt::query()
                ->where('provider_reference', $providerReference)
                ->first();
        }

        return null;
    }

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

    private function statusFromEventType(string $eventType): ?string
    {
        $normalized = strtolower(trim($eventType));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'settled') || str_contains($normalized, 'succeeded') || str_contains($normalized, 'paid')) {
            return 'settled';
        }

        if (str_contains($normalized, 'failed') || str_contains($normalized, 'declined')) {
            return 'failed';
        }

        if (str_contains($normalized, 'reversed') || str_contains($normalized, 'refund') || str_contains($normalized, 'chargeback')) {
            return 'reversed';
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodedPayload(string $body): ?array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
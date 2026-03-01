<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;

class RequestPayoutWebhookReconciliationService
{
    public function __construct(
        private readonly RequestPayoutExecutionAttemptProcessor $attemptProcessor,
    ) {
    }

    /**
     * @param  array<string,mixed>  $normalizedPayload
     */
    public function reconcile(
        ExecutionWebhookEvent $event,
        ?string $eventType,
        ?string $eventId,
        array $normalizedPayload
    ): array {
        $attempt = $this->resolveAttempt($normalizedPayload);

        if (! $attempt) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'No payout execution attempt matched webhook payload.',
                'processed_at' => now(),
            ])->save();

            return [
                'status' => 202,
                'ok' => true,
                'message' => 'Webhook accepted but no matching payout attempt was found.',
                'event_id' => (int) $event->id,
            ];
        }

        $event->forceFill([
            'company_id' => (int) $attempt->company_id,
            'request_payout_execution_attempt_id' => (int) $attempt->id,
        ])->save();

        $nextStatus = $this->statusFromEventType((string) $eventType);
        if ($nextStatus === null) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'Event type is not mapped to payout execution lifecycle.',
                'processed_at' => now(),
            ])->save();

            return [
                'status' => 202,
                'ok' => true,
                'message' => 'Webhook accepted but payout event type is not mapped.',
                'event_id' => (int) $event->id,
            ];
        }

        $this->attemptProcessor->markFromWebhook(
            attempt: $attempt,
            nextStatus: $nextStatus,
            eventId: $eventId,
            normalizedPayload: $normalizedPayload,
        );

        $event->forceFill([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'failure_reason' => null,
        ])->save();

        return [
            'status' => 202,
            'ok' => true,
            'message' => 'Webhook accepted and payout reconciled.',
            'event_id' => (int) $event->id,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveAttempt(array $payload): ?RequestPayoutExecutionAttempt
    {
        $attemptId = isset($payload['payout_attempt_id']) ? (int) $payload['payout_attempt_id'] : 0;
        if ($attemptId > 0) {
            return RequestPayoutExecutionAttempt::query()->find($attemptId);
        }

        $requestId = isset($payload['request_id']) ? (int) $payload['request_id'] : 0;
        if ($requestId > 0) {
            return RequestPayoutExecutionAttempt::query()->where('request_id', $requestId)->first();
        }

        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            return RequestPayoutExecutionAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
        }

        $externalTransferId = trim((string) ($payload['external_transfer_id'] ?? ''));
        if ($externalTransferId !== '') {
            return RequestPayoutExecutionAttempt::query()->where('external_transfer_id', $externalTransferId)->first();
        }

        $providerReference = trim((string) ($payload['provider_reference'] ?? ''));
        if ($providerReference !== '') {
            return RequestPayoutExecutionAttempt::query()->where('provider_reference', $providerReference)->first();
        }

        return null;
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
}
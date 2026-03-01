<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;
use Carbon\CarbonImmutable;

/**
 * Basic verifier for manual/placeholder providers.
 *
 * It supports optional HMAC signatures while keeping payload normalization consistent.
 */
class ManualOpsWebhookVerifier implements ProviderWebhookVerifierInterface
{
    public function providerKey(): string
    {
        return 'manual_ops';
    }

    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData
    {
        $decoded = json_decode($payload->body, true);
        if (! is_array($decoded)) {
            return new WebhookVerificationResultData(
                valid: false,
                reason: 'Webhook body must be valid JSON.',
            );
        }

        $secret = trim((string) config('execution.providers.manual_ops.webhook_secret', ''));
        if ($secret !== '') {
            $signature = trim((string) ($payload->signature ?? ''));
            if ($signature === '') {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Missing webhook signature.',
                );
            }

            $expected = hash_hmac('sha256', $payload->body, $secret);
            if (! hash_equals($expected, $signature)) {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Invalid webhook signature.',
                );
            }
        }

        $eventId = trim((string) ($decoded['event_id'] ?? $decoded['id'] ?? ''));
        $status = strtolower(trim((string) ($decoded['status'] ?? '')));
        $eventType = trim((string) ($decoded['event_type'] ?? $decoded['type'] ?? ''));
        if ($eventType === '' && $status !== '') {
            $eventType = 'billing.'.$status;
        }

        $occurredAt = null;
        $occurredAtRaw = trim((string) ($decoded['occurred_at'] ?? ''));
        if ($occurredAtRaw !== '') {
            try {
                $occurredAt = CarbonImmutable::parse($occurredAtRaw);
            } catch (\Throwable) {
                $occurredAt = null;
            }
        }

        return new WebhookVerificationResultData(
            valid: true,
            eventId: $eventId !== '' ? $eventId : null,
            eventType: $eventType !== '' ? $eventType : null,
            occurredAt: $occurredAt,
            normalizedPayload: [
                'billing_attempt_id' => isset($decoded['billing_attempt_id']) ? (int) $decoded['billing_attempt_id'] : null,
                'payout_attempt_id' => isset($decoded['payout_attempt_id']) ? (int) $decoded['payout_attempt_id'] : null,
                'request_id' => isset($decoded['request_id']) ? (int) $decoded['request_id'] : null,
                'idempotency_key' => (string) ($decoded['idempotency_key'] ?? ''),
                'external_invoice_id' => (string) ($decoded['external_invoice_id'] ?? ''),
                'external_transfer_id' => (string) ($decoded['external_transfer_id'] ?? ''),
                'provider_reference' => (string) ($decoded['provider_reference'] ?? ''),
                'status' => $status,
                'raw' => $decoded,
            ],
            reason: null,
        );
    }
}
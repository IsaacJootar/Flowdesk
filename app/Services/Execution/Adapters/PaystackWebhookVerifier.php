<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;
use Carbon\CarbonImmutable;

class PaystackWebhookVerifier implements ProviderWebhookVerifierInterface
{
    public function providerKey(): string
    {
        return 'paystack';
    }

    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData
    {
        $decoded = json_decode($payload->body, true);
        if (! is_array($decoded)) {
            return new WebhookVerificationResultData(
                valid: false,
                reason: 'Paystack webhook body must be valid JSON.',
            );
        }

        $secret = trim((string) config('execution.providers.paystack.secret_key', ''));
        $signature = trim((string) ($payload->signature ?: ($payload->headers['x-paystack-signature'] ?? '')));

        if ($secret !== '') {
            if ($signature === '') {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Missing Paystack webhook signature.',
                );
            }

            $computed = hash_hmac('sha512', $payload->body, $secret);
            if (! hash_equals($computed, $signature)) {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Invalid Paystack webhook signature.',
                );
            }
        }

        $eventName = strtolower(trim((string) ($decoded['event'] ?? '')));
        $data = is_array($decoded['data'] ?? null) ? (array) $decoded['data'] : [];
        $meta = is_array($data['metadata'] ?? null) ? (array) $data['metadata'] : [];

        $eventType = $this->normalizeEventType($eventName, $data);
        $eventId = trim((string) ($data['id'] ?? $data['reference'] ?? ''));

        return new WebhookVerificationResultData(
            valid: true,
            eventId: $eventId !== '' ? $eventId : null,
            eventType: $eventType,
            occurredAt: $this->occurredAt($data),
            normalizedPayload: [
                'billing_attempt_id' => isset($meta['billing_attempt_id']) ? (int) $meta['billing_attempt_id'] : null,
                'payout_attempt_id' => isset($meta['payout_attempt_id']) ? (int) $meta['payout_attempt_id'] : null,
                'request_id' => isset($meta['request_id']) ? (int) $meta['request_id'] : null,
                'idempotency_key' => (string) ($meta['idempotency_key'] ?? $data['reference'] ?? ''),
                'external_invoice_id' => (string) ($data['reference'] ?? ''),
                'external_transfer_id' => (string) ($data['transfer_code'] ?? $data['reference'] ?? ''),
                'provider_reference' => (string) ($data['reference'] ?? $data['transfer_code'] ?? ''),
                'status' => strtolower((string) ($data['status'] ?? '')),
                'raw' => $decoded,
            ],
            reason: null,
        );
    }

    private function normalizeEventType(string $eventName, array $data): ?string
    {
        $status = strtolower(trim((string) ($data['status'] ?? '')));

        if (str_starts_with($eventName, 'charge.')) {
            return match (true) {
                str_contains($eventName, 'success') => 'billing.settled',
                str_contains($eventName, 'failed') => 'billing.failed',
                str_contains($eventName, 'reversed'), str_contains($eventName, 'refund') => 'billing.reversed',
                default => 'billing.'.$status,
            };
        }

        if (str_starts_with($eventName, 'transfer.')) {
            return match (true) {
                str_contains($eventName, 'success') => 'payout.settled',
                str_contains($eventName, 'failed') => 'payout.failed',
                str_contains($eventName, 'reversed') => 'payout.reversed',
                default => 'payout.'.$status,
            };
        }

        return $eventName !== '' ? $eventName : null;
    }

    private function occurredAt(array $data): ?CarbonImmutable
    {
        $candidates = [
            (string) ($data['paid_at'] ?? ''),
            (string) ($data['created_at'] ?? ''),
            (string) ($data['updated_at'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($candidate);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}

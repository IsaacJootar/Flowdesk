<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;
use Carbon\CarbonImmutable;

class FlutterwaveWebhookVerifier implements ProviderWebhookVerifierInterface
{
    public function providerKey(): string
    {
        return 'flutterwave';
    }

    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData
    {
        $decoded = json_decode($payload->body, true);
        if (! is_array($decoded)) {
            return new WebhookVerificationResultData(
                valid: false,
                reason: 'Flutterwave webhook body must be valid JSON.',
            );
        }

        $headers = array_change_key_case((array) $payload->headers, CASE_LOWER);
        $verifHash = trim((string) ($headers['verif-hash'] ?? $headers['verif_hash'] ?? ''));
        $signature = trim((string) ($headers['flutterwave-signature'] ?? $payload->signature ?? ''));

        $secretHashes = $this->candidateSecretHashes();
        $secretKeys = $this->candidateSecretKeys();

        if ($secretHashes !== []) {
            $hashMatch = $verifHash !== '' && in_array($verifHash, $secretHashes, true);

            if (! $hashMatch) {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Invalid Flutterwave verif-hash header.',
                );
            }
        } elseif ($secretKeys !== []) {
            if ($signature === '') {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Missing Flutterwave webhook signature.',
                );
            }

            $isValid = false;
            foreach ($secretKeys as $key) {
                $computed = hash_hmac('sha256', $payload->body, $key);
                if (hash_equals($computed, $signature)) {
                    $isValid = true;
                    break;
                }
            }

            if (! $isValid) {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Invalid Flutterwave webhook signature.',
                );
            }
        }

        $eventName = strtolower(trim((string) ($decoded['event'] ?? '')));
        $data = is_array($decoded['data'] ?? null) ? (array) $decoded['data'] : [];
        $meta = is_array($data['meta'] ?? null) ? (array) $data['meta'] : [];

        $eventType = $this->normalizeEventType($eventName, $data);
        $eventId = trim((string) ($data['id'] ?? $data['flw_ref'] ?? $data['tx_ref'] ?? ''));

        return new WebhookVerificationResultData(
            valid: true,
            eventId: $eventId !== '' ? $eventId : null,
            eventType: $eventType,
            occurredAt: $this->occurredAt($data),
            normalizedPayload: [
                'billing_attempt_id' => isset($meta['billing_attempt_id']) ? (int) $meta['billing_attempt_id'] : null,
                'payout_attempt_id' => isset($meta['payout_attempt_id']) ? (int) $meta['payout_attempt_id'] : null,
                'request_id' => isset($meta['request_id']) ? (int) $meta['request_id'] : null,
                'idempotency_key' => (string) ($meta['idempotency_key'] ?? $data['tx_ref'] ?? $data['reference'] ?? ''),
                'external_invoice_id' => (string) ($data['id'] ?? $data['tx_ref'] ?? ''),
                'external_transfer_id' => (string) ($data['id'] ?? $data['reference'] ?? $data['tx_ref'] ?? ''),
                'provider_reference' => (string) ($data['flw_ref'] ?? $data['tx_ref'] ?? $data['reference'] ?? ''),
                'status' => strtolower((string) ($data['status'] ?? '')),
                'raw' => $decoded,
            ],
            reason: null,
        );
    }

    /**
     * @return array<int,string>
     */
    private function candidateSecretHashes(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $hash): string => trim((string) $hash),
            [
                config('execution.providers.flutterwave.webhook_secret_hash', ''),
                config('execution.providers.flutterwave.sandbox_webhook_secret_hash', ''),
            ]
        ))));
    }

    /**
     * @return array<int,string>
     */
    private function candidateSecretKeys(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => trim((string) $key),
            [
                config('execution.providers.flutterwave.secret_key', ''),
                config('execution.providers.flutterwave.sandbox_secret_key', ''),
            ]
        ))));
    }

    private function normalizeEventType(string $eventName, array $data): ?string
    {
        $status = strtolower(trim((string) ($data['status'] ?? '')));

        if (str_contains($eventName, 'transfer')) {
            return match (true) {
                in_array($status, ['successful', 'success', 'completed'], true), str_contains($eventName, 'completed') => 'payout.settled',
                in_array($status, ['failed'], true), str_contains($eventName, 'failed') => 'payout.failed',
                in_array($status, ['reversed'], true), str_contains($eventName, 'reversed') => 'payout.reversed',
                default => 'payout.'.$status,
            };
        }

        if (str_contains($eventName, 'charge') || str_contains($eventName, 'payment')) {
            return match (true) {
                in_array($status, ['successful', 'success', 'completed'], true), str_contains($eventName, 'completed') => 'billing.settled',
                in_array($status, ['failed'], true), str_contains($eventName, 'failed') => 'billing.failed',
                in_array($status, ['reversed', 'refund'], true), str_contains($eventName, 'reversed') => 'billing.reversed',
                default => 'billing.'.$status,
            };
        }

        return $eventName !== '' ? $eventName : null;
    }

    private function occurredAt(array $data): ?CarbonImmutable
    {
        $candidates = [
            (string) ($data['created_at'] ?? ''),
            (string) ($data['updated_at'] ?? ''),
            (string) ($data['completed_at'] ?? ''),
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
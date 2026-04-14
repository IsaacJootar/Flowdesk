<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;
use Carbon\CarbonImmutable;

/**
 * Webhook signature verifier and payload normalizer for Mono.
 *
 * Mono signs webhook payloads using HMAC-SHA512.
 * The signature is delivered in the `mono-webhook-secret` header.
 *
 * Relevant Mono webhook event types:
 *   Disbursements: mono.events.disbursement_successful, mono.events.disbursement_failed
 *   DirectPay:     mono.directpay.payment.paid, mono.directpay.payment.failed
 *   Connect:       mono.events.account_updated (balance/transaction sync signals)
 *
 * API reference: https://docs.mono.co/api/webhooks
 */
class MonoWebhookVerifier implements ProviderWebhookVerifierInterface
{
    public function providerKey(): string
    {
        return 'mono';
    }

    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData
    {
        $decoded = json_decode($payload->body, true);
        if (! is_array($decoded)) {
            return new WebhookVerificationResultData(
                valid: false,
                reason: 'Mono webhook body must be valid JSON.',
            );
        }

        $headers   = array_change_key_case((array) $payload->headers, CASE_LOWER);
        // Mono sends signature in `mono-webhook-secret` header
        $signature = trim((string) ($payload->signature ?: ($headers['mono-webhook-secret'] ?? '')));
        $secrets   = $this->signingSecrets();

        if ($secrets !== []) {
            if ($signature === '') {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Missing Mono webhook signature header (mono-webhook-secret).',
                );
            }

            $isValid = false;
            foreach ($secrets as $secret) {
                $computed = hash_hmac('sha512', $payload->body, $secret);
                if (hash_equals($computed, $signature)) {
                    $isValid = true;
                    break;
                }
            }

            if (! $isValid) {
                return new WebhookVerificationResultData(
                    valid: false,
                    reason: 'Invalid Mono webhook signature.',
                );
            }
        }

        // Mono wraps the event name in `event` at the top level
        $eventName = strtolower(trim((string) ($decoded['event'] ?? '')));
        $data      = is_array($decoded['data'] ?? null) ? (array) $decoded['data'] : [];
        $meta      = is_array($data['meta']     ?? null) ? (array) $data['meta']     : [];

        $eventType = $this->normalizeEventType($eventName, $data);

        // Mono uses `_id` as its document identifier; reference field varies by product
        $eventId = trim((string) (
            $data['_id']       ??
            $data['id']        ??
            $data['reference'] ??
            ($decoded['_id']   ?? '')
        ));

        return new WebhookVerificationResultData(
            valid: true,
            eventId: $eventId !== '' ? $eventId : null,
            eventType: $eventType,
            occurredAt: $this->occurredAt($data, $decoded),
            normalizedPayload: [
                // Internal linkage — populated from metadata set during initiation
                'billing_attempt_id'  => isset($meta['billing_attempt_id'])  ? (int) $meta['billing_attempt_id']  : null,
                'payout_attempt_id'   => isset($meta['payout_attempt_id'])   ? (int) $meta['payout_attempt_id']   : null,
                'request_id'          => isset($meta['request_id'])          ? (int) $meta['request_id']          : null,
                'idempotency_key'     => (string) ($meta['idempotency_key']  ?? $data['reference'] ?? ''),
                // External references
                'external_invoice_id' => (string) ($data['reference']  ?? $data['_id'] ?? ''),
                'external_transfer_id'=> (string) ($data['_id']        ?? $data['reference'] ?? ''),
                'provider_reference'  => (string) ($data['reference']  ?? $data['_id']        ?? ''),
                'status'              => strtolower((string) ($data['status'] ?? '')),
                'raw'                 => $decoded,
            ],
            reason: null,
        );
    }

    /**
     * @return array<int,string>
     */
    private function signingSecrets(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $s): string => trim((string) $s),
            [
                config('execution.providers.mono.webhook_secret',         ''),
                config('execution.providers.mono.sandbox_webhook_secret', ''),
            ]
        ))));
    }

    /**
     * Map Mono's verbose event names to Flowdesk's internal two-segment format
     * (billing.settled, payout.settled, payout.failed, etc.)
     */
    private function normalizeEventType(string $eventName, array $data): ?string
    {
        $status = strtolower(trim((string) ($data['status'] ?? '')));

        // Disbursement events: mono.events.disbursement_successful / disbursement_failed
        if (str_contains($eventName, 'disbursement')) {
            return match (true) {
                str_contains($eventName, 'successful') || str_contains($eventName, 'success') => 'payout.settled',
                str_contains($eventName, 'failed')                                             => 'payout.failed',
                str_contains($eventName, 'reversed')                                           => 'payout.reversed',
                default                                                                        => 'payout.' . $status,
            };
        }

        // DirectPay events: mono.directpay.payment.paid / mono.directpay.payment.failed
        if (str_contains($eventName, 'directpay') || str_contains($eventName, 'payment')) {
            return match (true) {
                str_contains($eventName, 'paid')    || $status === 'paid'    => 'billing.settled',
                str_contains($eventName, 'failed')  || $status === 'failed'  => 'billing.failed',
                str_contains($eventName, 'refund')  || $status === 'refunded' => 'billing.reversed',
                default                                                       => 'billing.' . $status,
            };
        }

        // Connect account update signals (balance/transaction sync)
        if (str_contains($eventName, 'account_updated') || str_contains($eventName, 'account.updated')) {
            return 'connect.account_updated';
        }

        return $eventName !== '' ? $eventName : null;
    }

    private function occurredAt(array $data, array $decoded): ?CarbonImmutable
    {
        $candidates = [
            (string) ($data['updatedAt']   ?? ''),
            (string) ($data['createdAt']   ?? ''),
            (string) ($data['updated_at']  ?? ''),
            (string) ($data['created_at']  ?? ''),
            (string) ($decoded['timestamp'] ?? ''),
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

<?php

namespace Tests\Fakes\Execution;

use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;
use Carbon\CarbonImmutable;

class FakeWebhookVerifier implements ProviderWebhookVerifierInterface
{
    public function providerKey(): string
    {
        return 'fake';
    }

    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData
    {
        $decoded = json_decode($payload->body, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        return new WebhookVerificationResultData(
            valid: true,
            eventId: (string) ($decoded['event_id'] ?? 'evt-fake-001'),
            eventType: (string) ($decoded['event_type'] ?? 'payment.settled'),
            occurredAt: CarbonImmutable::now(),
            normalizedPayload: [
                'billing_attempt_id' => isset($decoded['billing_attempt_id']) ? (int) $decoded['billing_attempt_id'] : null,
                'payout_attempt_id' => isset($decoded['payout_attempt_id']) ? (int) $decoded['payout_attempt_id'] : null,
                'request_id' => isset($decoded['request_id']) ? (int) $decoded['request_id'] : null,
                'idempotency_key' => (string) ($decoded['idempotency_key'] ?? ''),
                'external_invoice_id' => (string) ($decoded['external_invoice_id'] ?? ''),
                'external_transfer_id' => (string) ($decoded['external_transfer_id'] ?? ''),
                'provider_reference' => (string) ($decoded['provider_reference'] ?? ''),
                'status' => (string) ($decoded['status'] ?? 'settled'),
                'raw' => $decoded,
            ],
            reason: null,
        );
    }
}
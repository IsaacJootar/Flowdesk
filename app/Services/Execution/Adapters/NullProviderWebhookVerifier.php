<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;

/**
 * Default webhook verifier for providers not yet integrated.
 */
class NullProviderWebhookVerifier implements ProviderWebhookVerifierInterface
{
    public function providerKey(): string
    {
        return 'null';
    }

    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData
    {
        return new WebhookVerificationResultData(
            valid: false,
            eventId: null,
            eventType: null,
            occurredAt: null,
            normalizedPayload: [
                'provider' => $payload->provider,
            ],
            reason: 'No webhook verifier configured for provider: '.$payload->provider,
        );
    }
}

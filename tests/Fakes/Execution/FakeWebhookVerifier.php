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
        return new WebhookVerificationResultData(
            valid: true,
            eventId: 'evt-fake-001',
            eventType: 'payment.settled',
            occurredAt: CarbonImmutable::now(),
            normalizedPayload: ['body' => $payload->body],
            reason: null,
        );
    }
}

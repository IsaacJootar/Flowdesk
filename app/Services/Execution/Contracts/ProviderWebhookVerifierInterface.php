<?php

namespace App\Services\Execution\Contracts;

use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use App\Services\Execution\DTO\WebhookVerificationResultData;

interface ProviderWebhookVerifierInterface
{
    /**
     * Stable provider key used in config/tenant execution_provider.
     */
    public function providerKey(): string;

    /**
     * Verify signature and normalize webhook into common shape.
     */
    public function verify(ProviderWebhookPayloadData $payload): WebhookVerificationResultData;
}

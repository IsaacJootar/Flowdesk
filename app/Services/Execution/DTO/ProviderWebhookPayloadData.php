<?php

namespace App\Services\Execution\DTO;

use Carbon\CarbonImmutable;

/**
 * Raw webhook payload passed through provider verifier contract.
 */
final readonly class ProviderWebhookPayloadData
{
    /**
     * @param  array<string,string>  $headers
     */
    public function __construct(
        public string $provider,
        public string $body,
        public array $headers = [],
        public ?string $signature = null,
        public ?CarbonImmutable $receivedAt = null,
    ) {
    }
}

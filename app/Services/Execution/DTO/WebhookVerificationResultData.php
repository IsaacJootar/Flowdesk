<?php

namespace App\Services\Execution\DTO;

use Carbon\CarbonImmutable;

/**
 * Normalized webhook verification output returned by provider verifiers.
 */
final readonly class WebhookVerificationResultData
{
    /**
     * @param  array<string,mixed>  $normalizedPayload
     */
    public function __construct(
        public bool $valid,
        public ?string $eventId = null,
        public ?string $eventType = null,
        public ?CarbonImmutable $occurredAt = null,
        public array $normalizedPayload = [],
        public ?string $reason = null,
    ) {
    }
}

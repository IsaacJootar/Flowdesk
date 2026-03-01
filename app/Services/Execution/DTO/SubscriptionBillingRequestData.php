<?php

namespace App\Services\Execution\DTO;

use Carbon\CarbonImmutable;

/**
 * Canonical payload for subscription billing adapter operations.
 */
final readonly class SubscriptionBillingRequestData
{
    /**
     * @param  array<string,mixed>  $metadata
     */
    public function __construct(
        public int $companyId,
        public int $subscriptionId,
        public string $planCode,
        public float $amount,
        public string $currencyCode,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {
    }
}

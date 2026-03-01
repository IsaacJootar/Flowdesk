<?php

namespace App\Services\Execution\DTO;

/**
 * Canonical payload for payout execution calls.
 */
final readonly class PayoutExecutionRequestData
{
    /**
     * @param  array<string,mixed>  $beneficiary
     * @param  array<string,mixed>  $metadata
     */
    public function __construct(
        public int $companyId,
        public ?int $requestId,
        public float $amount,
        public string $currencyCode,
        public string $channel,
        public array $beneficiary,
        public string $idempotencyKey,
        public ?string $narration = null,
        public array $metadata = [],
    ) {
    }
}

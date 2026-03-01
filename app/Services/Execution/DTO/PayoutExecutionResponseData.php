<?php

namespace App\Services\Execution\DTO;

/**
 * Payout adapter response wrapper.
 */
final readonly class PayoutExecutionResponseData
{
    public function __construct(
        public AdapterOperationResult $result,
        public ?string $externalTransferId = null,
    ) {
    }
}

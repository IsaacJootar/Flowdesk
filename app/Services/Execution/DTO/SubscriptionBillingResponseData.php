<?php

namespace App\Services\Execution\DTO;

/**
 * Billing adapter response wrapper.
 */
final readonly class SubscriptionBillingResponseData
{
    public function __construct(
        public AdapterOperationResult $result,
        public ?string $externalInvoiceId = null,
    ) {
    }
}

<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\SubscriptionBillingAdapterInterface;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\SubscriptionBillingRequestData;
use App\Services\Execution\DTO\SubscriptionBillingResponseData;

/**
 * Safe no-op adapter for decision-only or unconfigured tenants.
 */
class NullSubscriptionBillingAdapter implements SubscriptionBillingAdapterInterface
{
    public function providerKey(): string
    {
        return 'null';
    }

    public function billTenant(SubscriptionBillingRequestData $request): SubscriptionBillingResponseData
    {
        return new SubscriptionBillingResponseData(
            result: new AdapterOperationResult(
                status: AdapterOperationStatus::Skipped,
                success: true,
                providerReference: null,
                raw: [
                    'reason' => 'Billing adapter is disabled or not configured for this tenant.',
                    'provider' => $this->providerKey(),
                ],
            ),
            externalInvoiceId: null,
        );
    }
}

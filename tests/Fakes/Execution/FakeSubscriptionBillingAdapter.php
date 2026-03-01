<?php

namespace Tests\Fakes\Execution;

use App\Services\Execution\Contracts\SubscriptionBillingAdapterInterface;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\SubscriptionBillingRequestData;
use App\Services\Execution\DTO\SubscriptionBillingResponseData;

class FakeSubscriptionBillingAdapter implements SubscriptionBillingAdapterInterface
{
    public function providerKey(): string
    {
        return 'fake';
    }

    public function billTenant(SubscriptionBillingRequestData $request): SubscriptionBillingResponseData
    {
        return new SubscriptionBillingResponseData(
            result: new AdapterOperationResult(
                status: AdapterOperationStatus::Queued,
                success: true,
                providerReference: 'fake-billing-ref',
                raw: ['company_id' => $request->companyId],
            ),
            externalInvoiceId: 'inv-fake-001',
        );
    }
}

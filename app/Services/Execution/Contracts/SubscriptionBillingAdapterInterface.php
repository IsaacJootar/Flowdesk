<?php

namespace App\Services\Execution\Contracts;

use App\Services\Execution\DTO\SubscriptionBillingRequestData;
use App\Services\Execution\DTO\SubscriptionBillingResponseData;

interface SubscriptionBillingAdapterInterface
{
    /**
     * Stable provider key used in config/tenant execution_provider.
     */
    public function providerKey(): string;

    /**
     * Execute a subscription billing attempt through provider API.
     */
    public function billTenant(SubscriptionBillingRequestData $request): SubscriptionBillingResponseData;
}

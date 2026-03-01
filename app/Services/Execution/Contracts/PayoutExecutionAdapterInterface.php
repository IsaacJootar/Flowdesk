<?php

namespace App\Services\Execution\Contracts;

use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\Execution\DTO\PayoutExecutionResponseData;

interface PayoutExecutionAdapterInterface
{
    /**
     * Stable provider key used in config/tenant execution_provider.
     */
    public function providerKey(): string;

    /**
     * Execute payout request through provider API.
     */
    public function executePayout(PayoutExecutionRequestData $request): PayoutExecutionResponseData;
}

<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\PayoutExecutionAdapterInterface;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\Execution\DTO\PayoutExecutionResponseData;

/**
 * Safe no-op payout adapter for decision-only or unconfigured tenants.
 */
class NullPayoutExecutionAdapter implements PayoutExecutionAdapterInterface
{
    public function providerKey(): string
    {
        return 'null';
    }

    public function executePayout(PayoutExecutionRequestData $request): PayoutExecutionResponseData
    {
        return new PayoutExecutionResponseData(
            result: new AdapterOperationResult(
                status: AdapterOperationStatus::Skipped,
                success: true,
                providerReference: null,
                raw: [
                    'reason' => 'Payout execution adapter is disabled or not configured for this tenant.',
                    'provider' => $this->providerKey(),
                ],
            ),
            externalTransferId: null,
        );
    }
}

<?php

namespace Tests\Fakes\Execution;

use App\Services\Execution\Contracts\PayoutExecutionAdapterInterface;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\Execution\DTO\PayoutExecutionResponseData;

class FakePayoutExecutionAdapter implements PayoutExecutionAdapterInterface
{
    public function providerKey(): string
    {
        return 'fake';
    }

    public function executePayout(PayoutExecutionRequestData $request): PayoutExecutionResponseData
    {
        return new PayoutExecutionResponseData(
            result: new AdapterOperationResult(
                status: AdapterOperationStatus::Processing,
                success: true,
                providerReference: 'fake-transfer-ref',
                raw: ['request_id' => $request->requestId],
            ),
            externalTransferId: 'xfer-fake-001',
        );
    }
}

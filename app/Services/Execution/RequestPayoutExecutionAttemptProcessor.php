<?php

namespace App\Services\Execution;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\RequestCommunicationLogger;
use App\Services\Treasury\SyncPayoutExecutionExceptionService;

class RequestPayoutExecutionAttemptProcessor
{
    public function __construct(
        private readonly TenantExecutionAdapterFactory $adapterFactory,
        private readonly RequestCommunicationLogger $requestCommunicationLogger,
        private readonly SyncPayoutExecutionExceptionService $syncPayoutExecutionExceptionService,
    ) {
    }

    public function processAttemptById(int $attemptId): bool
    {
        $attempt = RequestPayoutExecutionAttempt::query()
            ->with(['subscription'])
            ->find($attemptId);

        if (! $attempt) {
            return false;
        }

        if (in_array((string) $attempt->execution_status, ['settled', 'reversed'], true)) {
            return false;
        }

        $request = SpendRequest::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->with(['requester', 'vendor'])
            ->find((int) $attempt->request_id);

        $subscription = $attempt->subscription;
        if (! $request || ! $subscription) {
            return false;
        }

        // Preserve the unscoped request relation for downstream status sync.
        $attempt->setRelation('request', $request);

        $attempt->forceFill([
            'execution_status' => 'processing',
            'processed_at' => now(),
            'attempt_count' => max(1, (int) $attempt->attempt_count) + ((string) $attempt->execution_status === 'queued' ? 0 : 1),
        ])->save();

        $request->forceFill([
            'status' => 'execution_processing',
            'updated_by' => $attempt->updated_by,
        ])->save();

        $vendor = $request->vendor;
        $attemptMetadata = (array) ($attempt->metadata ?? []);
        // Keep a deterministic fallback from attempt metadata so retries still work if vendor profile changes later.
        $accountNumber = trim((string) ($vendor?->account_number ?? $attemptMetadata['account_number'] ?? ''));
        $bankCode = trim((string) ($vendor?->bank_code ?? $attemptMetadata['bank_code'] ?? ''));
        $beneficiaryName = trim((string) ($vendor?->account_name ?? $vendor?->name ?? $attemptMetadata['beneficiary_name'] ?? ''));

        $adapter = $this->adapterFactory->payoutAdapterForSubscription($subscription);
        $response = $adapter->executePayout(new PayoutExecutionRequestData(
            companyId: (int) $attempt->company_id,
            requestId: (int) $attempt->request_id,
            amount: (float) $attempt->amount,
            currencyCode: strtoupper((string) $attempt->currency_code),
            channel: (string) $attempt->execution_channel,
            beneficiary: [
                'vendor_id' => (int) ($request->vendor_id ?? 0),
                'vendor_name' => (string) ($vendor?->name ?? ''),
                'name' => $beneficiaryName,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'recipient_code' => (string) ($attemptMetadata['recipient_code'] ?? ''),
            ],
            idempotencyKey: (string) $attempt->idempotency_key,
            narration: (string) $request->title,
            metadata: [
                'request_code' => (string) $request->request_code,
                'payout_attempt_id' => (int) $attempt->id,
                'beneficiary_name' => $beneficiaryName,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ],
        ));

        $this->applyAdapterResult($attempt, $response->result, $response->externalTransferId);

        return true;
    }

    public function markFromWebhook(
        RequestPayoutExecutionAttempt $attempt,
        string $nextStatus,
        ?string $eventId,
        array $normalizedPayload = []
    ): void {
        $request = SpendRequest::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->find((int) $attempt->request_id);
        $attempt->forceFill([
            'execution_status' => $nextStatus,
            'last_provider_event_id' => $eventId,
            'provider_response' => $normalizedPayload,
            'processed_at' => now(),
            'settled_at' => $nextStatus === 'settled' ? now() : $attempt->settled_at,
            'failed_at' => $nextStatus === 'failed' ? now() : $attempt->failed_at,
        ])->save();

        $this->syncRequestStatus($request, $nextStatus, $attempt);
        $this->syncPayoutExecutionExceptionService->syncForAttempt($attempt, 'webhook');
    }

    private function applyAdapterResult(
        RequestPayoutExecutionAttempt $attempt,
        AdapterOperationResult $result,
        ?string $externalTransferId
    ): void {
        $status = match ($result->status) {
            AdapterOperationStatus::Settled => 'settled',
            AdapterOperationStatus::Failed => 'failed',
            AdapterOperationStatus::Reversed => 'reversed',
            AdapterOperationStatus::Skipped => 'skipped',
            AdapterOperationStatus::Queued, AdapterOperationStatus::Processing => 'webhook_pending',
        };

        $attempt->forceFill([
            'execution_status' => $status,
            'external_transfer_id' => $externalTransferId ?: $attempt->external_transfer_id,
            'provider_reference' => $result->providerReference ?: $attempt->provider_reference,
            'provider_response' => $result->raw,
            'error_code' => $result->error?->code,
            'error_message' => $result->error?->message,
            'processed_at' => now(),
            'settled_at' => $status === 'settled' ? now() : $attempt->settled_at,
            'failed_at' => $status === 'failed' ? now() : $attempt->failed_at,
        ])->save();

        $this->syncRequestStatus($attempt->request, $status, $attempt);
        // Terminal payout outcomes must be mirrored into treasury exception queue for incident triage.
        $this->syncPayoutExecutionExceptionService->syncForAttempt($attempt, 'adapter');
    }

    private function syncRequestStatus(?SpendRequest $request, string $executionStatus, RequestPayoutExecutionAttempt $attempt): void
    {
        if (! $request) {
            return;
        }

        $requestStatus = match ($executionStatus) {
            'queued' => 'execution_queued',
            'processing', 'webhook_pending' => 'execution_processing',
            'settled' => 'settled',
            'failed' => 'failed',
            'reversed' => 'reversed',
            'skipped' => 'approved_for_execution',
            default => 'execution_processing',
        };

        $request->forceFill([
            'status' => $requestStatus,
            'decided_at' => in_array($requestStatus, ['settled', 'failed', 'reversed'], true) ? now() : $request->decided_at,
            'metadata' => array_merge((array) ($request->metadata ?? []), [
                'execution' => [
                    'payout_attempt_id' => (int) $attempt->id,
                    'execution_status' => $executionStatus,
                    'updated_at' => now()->toDateTimeString(),
                ],
            ]),
        ])->save();

        // Notify requester on terminal execution outcomes.
        if (in_array($requestStatus, ['settled', 'failed', 'reversed'], true)) {
            $this->requestCommunicationLogger->log(
                request: $request,
                event: 'request.execution.'.$requestStatus,
                channels: ['in_app'],
                recipientUserIds: [(int) $request->requested_by],
                requestApprovalId: null,
                metadata: [
                    'request_code' => (string) $request->request_code,
                    'execution_status' => $executionStatus,
                ]
            );
        }
    }
}

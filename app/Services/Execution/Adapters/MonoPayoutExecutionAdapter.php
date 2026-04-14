<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\PayoutExecutionAdapterInterface;
use App\Services\Execution\DTO\AdapterErrorData;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\Execution\DTO\PayoutExecutionResponseData;
use App\Services\Mono\MonoAccountVerificationService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Payout execution adapter for Mono Disbursements API.
 *
 * Mono's payout product verifies the destination account before transfer,
 * which reduces failed payouts caused by stale or mistyped vendor bank details.
 *
 * API reference: https://docs.mono.co/api/disbursements
 * Auth header:   mono-sec-key: sk_live_... (NOT Bearer)
 * Amounts:       major currency units (e.g. 5000 = NGN 5,000)
 */
class MonoPayoutExecutionAdapter implements PayoutExecutionAdapterInterface
{
    public function __construct(
        private readonly MonoAccountVerificationService $accountVerifier,
    ) {}

    public function providerKey(): string
    {
        return 'mono';
    }

    public function executePayout(PayoutExecutionRequestData $request): PayoutExecutionResponseData
    {
        $secretKey = trim((string) config('execution.providers.mono.secret_key', ''));
        $baseUrl   = rtrim((string) config('execution.providers.mono.base_url', 'https://api.withmono.com'), '/');

        if ($secretKey === '') {
            return $this->failed(
                code: 'mono_not_configured',
                message: 'Mono payout is not configured. Missing secret key.',
                retryable: false
            );
        }

        // Resolve beneficiary fields from the request
        $beneficiary    = $request->beneficiary;
        $accountNumber  = trim((string) ($beneficiary['account_number'] ?? $request->metadata['account_number'] ?? ''));
        $bankCode       = trim((string) ($beneficiary['bank_code']      ?? $request->metadata['bank_code']      ?? ''));
        $accountName    = trim((string) ($beneficiary['name']           ?? $beneficiary['vendor_name']          ?? $request->metadata['beneficiary_name'] ?? ''));

        if ($accountNumber === '' || $bankCode === '') {
            return $this->failed(
                code: 'beneficiary_data_missing',
                message: 'Missing beneficiary account_number and bank_code for Mono payout.',
                retryable: false
            );
        }

        // Verify the destination account before initiating transfer.
        // This is Mono's key advantage — it catches invalid accounts upfront
        // instead of generating a failed transfer and a reconciliation exception.
        $verification = $this->accountVerifier->verify($accountNumber, $bankCode);
        if (! $verification['valid']) {
            return $this->failed(
                code: 'account_verification_failed',
                message: (string) ($verification['message'] ?? 'Destination account could not be verified by Mono.'),
                retryable: false,
                details: ['account_number' => $accountNumber, 'bank_code' => $bankCode]
            );
        }

        // Use verified account name if none was supplied
        if ($accountName === '') {
            $accountName = (string) ($verification['account_name'] ?? 'Flowdesk Beneficiary');
        }

        if ($request->amount <= 0) {
            return $this->failed(
                code: 'invalid_amount',
                message: 'Payout amount must be greater than zero.',
                retryable: false
            );
        }

        // Mono Disbursements API — amounts are in major units (not kobo)
        $payload = [
            'amount'      => round($request->amount, 2),
            'type'        => 'bank',
            'reference'   => $request->idempotencyKey,
            'narration'   => trim((string) ($request->narration ?? 'Flowdesk payout')),
            'destination' => [
                'account_number' => $accountNumber,
                'bank_code'      => $bankCode,
                'account_name'   => $accountName,
            ],
            'meta' => [
                'company_id'       => $request->companyId,
                'request_id'       => $request->requestId,
                'idempotency_key'  => $request->idempotencyKey,
            ],
        ];

        try {
            $response = Http::timeout(25)
                ->withHeaders(['mono-sec-key' => $secretKey])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl . '/v2/disbursements', $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            $responseStatus = strtolower(trim((string) ($json['status'] ?? '')));
            $accepted = in_array($responseStatus, ['successful', 'success'], true);

            if (! $response->successful() || ! $accepted) {
                return $this->failed(
                    code: 'mono_payout_request_failed',
                    message: (string) ($json['message'] ?? 'Mono payout request failed.'),
                    retryable: $response->status() >= 500 || $response->status() === 429,
                    providerReference: (string) ($json['data']['reference'] ?? $request->idempotencyKey),
                    details: ['http_status' => $response->status(), 'response' => $json]
                );
            }

            $data             = is_array($json['data'] ?? null) ? (array) $json['data'] : [];
            $providerRef      = trim((string) ($data['reference'] ?? $request->idempotencyKey));
            $externalId       = trim((string) ($data['id']        ?? $providerRef));

            // Map Mono disbursement statuses to internal lifecycle
            $dataStatus = strtolower(trim((string) ($data['status'] ?? '')));
            $status = match (true) {
                in_array($dataStatus, ['successful', 'success', 'completed'], true) => AdapterOperationStatus::Settled,
                in_array($dataStatus, ['failed'],                             true) => AdapterOperationStatus::Failed,
                in_array($dataStatus, ['reversed', 'refunded'],               true) => AdapterOperationStatus::Reversed,
                in_array($dataStatus, ['pending', 'processing'],              true) => AdapterOperationStatus::Processing,
                default                                                              => AdapterOperationStatus::Queued,
            };

            return new PayoutExecutionResponseData(
                result: new AdapterOperationResult(
                    status: $status,
                    success: $status !== AdapterOperationStatus::Failed,
                    providerReference: $providerRef !== '' ? $providerRef : null,
                    raw: [
                        'provider' => $this->providerKey(),
                        'message'  => (string) ($json['message'] ?? 'Disbursement initiated.'),
                        'response' => $json,
                    ],
                    error: $status === AdapterOperationStatus::Failed
                        ? new AdapterErrorData(
                            code: 'mono_payout_failed',
                            message: (string) ($json['message'] ?? 'Mono payout failed.'),
                            retryable: true,
                            providerReference: $providerRef !== '' ? $providerRef : null,
                            details: ['response' => $json],
                        )
                        : null,
                ),
                externalTransferId: $externalId !== '' ? $externalId : ($providerRef !== '' ? $providerRef : null),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed(
                code: 'mono_payout_exception',
                message: 'Mono payout request threw an exception.',
                retryable: true,
                details: ['error' => $exception->getMessage()]
            );
        }
    }

    /**
     * @param array<string,mixed> $details
     */
    private function failed(
        string $code,
        string $message,
        bool $retryable,
        ?string $providerReference = null,
        array $details = [],
    ): PayoutExecutionResponseData {
        return new PayoutExecutionResponseData(
            result: new AdapterOperationResult(
                status: AdapterOperationStatus::Failed,
                success: false,
                providerReference: $providerReference,
                raw: [
                    'provider' => $this->providerKey(),
                    'message'  => $message,
                    'details'  => $details,
                ],
                error: new AdapterErrorData(
                    code: $code,
                    message: $message,
                    retryable: $retryable,
                    providerReference: $providerReference,
                    details: $details,
                ),
            ),
            externalTransferId: null,
        );
    }
}

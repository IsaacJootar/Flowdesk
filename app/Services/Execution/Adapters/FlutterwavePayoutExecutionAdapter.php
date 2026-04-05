<?php

namespace App\Services\Execution\Adapters;

use App\Services\Execution\Contracts\PayoutExecutionAdapterInterface;
use App\Services\Execution\DTO\AdapterErrorData;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\Execution\DTO\PayoutExecutionResponseData;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Adapter for executing payouts via Flutterwave payment provider.
 */
class FlutterwavePayoutExecutionAdapter implements PayoutExecutionAdapterInterface
{
    public function providerKey(): string
    {
        return 'flutterwave';
    }

    public function executePayout(PayoutExecutionRequestData $request): PayoutExecutionResponseData
    {
        // Get configuration
        $secret = trim((string) config('execution.providers.flutterwave.secret_key', ''));
        $baseUrl = rtrim((string) config('execution.providers.flutterwave.base_url', 'https://api.flutterwave.com/v3'), '/');

        if ($secret === '') {
            return $this->failed(
                code: 'flutterwave_not_configured',
                message: 'Flutterwave payout is not configured. Missing secret key.',
                retryable: false
            );
        }

        // Extract beneficiary data
        $beneficiary = $request->beneficiary;
        $accountNumber = trim((string) ($beneficiary['account_number'] ?? $request->metadata['account_number'] ?? ''));
        $bankCode = trim((string) ($beneficiary['bank_code'] ?? $request->metadata['bank_code'] ?? ''));
        $beneficiaryName = trim((string) ($beneficiary['name'] ?? $beneficiary['vendor_name'] ?? $request->metadata['beneficiary_name'] ?? 'Flowdesk Beneficiary'));

        if ($accountNumber === '' || $bankCode === '') {
            return $this->failed(
                code: 'beneficiary_data_missing',
                message: 'Missing beneficiary account_number + bank_code for Flutterwave payout.',
                retryable: false
            );
        }

        // Build payload
        $payload = [
            'account_bank' => $bankCode,
            'account_number' => $accountNumber,
            'amount' => round($request->amount, 2),
            'currency' => strtoupper($request->currencyCode),
            'narration' => trim((string) ($request->narration ?? 'Flowdesk payout execution')),
            'reference' => $request->idempotencyKey,
            'beneficiary_name' => $beneficiaryName,
            'meta' => array_merge($request->metadata, [
                'request_id' => $request->requestId,
                'idempotency_key' => $request->idempotencyKey,
            ]),
        ];

        try {
            // Make API request
            $response = Http::timeout(25)
                ->withToken($secret)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/transfers', $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            $providerStatus = strtolower((string) ($json['status'] ?? ''));
            $requestAccepted = in_array($providerStatus, ['success', 'successful'], true);

            if (! $response->successful() || ! $requestAccepted) {
                return $this->failed(
                    code: 'flutterwave_payout_request_failed',
                    message: (string) ($json['message'] ?? 'Flutterwave payout request failed.'),
                    retryable: $response->status() >= 500 || $response->status() === 429,
                    providerReference: (string) (($json['data']['reference'] ?? '') ?: $request->idempotencyKey),
                    details: [
                        'http_status' => $response->status(),
                        'response' => $json,
                    ]
                );
            }

            // Determine status from response
            $dataStatus = strtolower((string) ($json['data']['status'] ?? ''));
            $status = match (true) {
                in_array($dataStatus, ['successful', 'success', 'completed'], true) => AdapterOperationStatus::Settled,
                in_array($dataStatus, ['failed'], true) => AdapterOperationStatus::Failed,
                in_array($dataStatus, ['reversed'], true) => AdapterOperationStatus::Reversed,
                in_array($dataStatus, ['new', 'pending', 'processing'], true) => AdapterOperationStatus::Processing,
                default => AdapterOperationStatus::Queued,
            };

            $providerReference = trim((string) (($json['data']['reference'] ?? '') ?: $request->idempotencyKey));
            $externalTransferId = trim((string) (($json['data']['id'] ?? '') ?: $providerReference));

            return new PayoutExecutionResponseData(
                result: new AdapterOperationResult(
                    status: $status,
                    success: $status !== AdapterOperationStatus::Failed,
                    providerReference: $providerReference !== '' ? $providerReference : null,
                    raw: [
                        'provider' => $this->providerKey(),
                        'message' => (string) ($json['message'] ?? 'Transfer initiated.'),
                        'response' => $json,
                    ],
                    error: $status === AdapterOperationStatus::Failed
                        ? new AdapterErrorData(
                            code: 'flutterwave_payout_failed',
                            message: (string) ($json['message'] ?? 'Flutterwave payout failed.'),
                            retryable: true,
                            providerReference: $providerReference !== '' ? $providerReference : null,
                            details: ['response' => $json],
                        )
                        : null,
                ),
                externalTransferId: $externalTransferId !== '' ? $externalTransferId : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed(
                code: 'flutterwave_payout_exception',
                message: 'Flutterwave payout request threw an exception.',
                retryable: true,
                details: ['error' => $exception->getMessage()]
            );
        }
    }

    /**
     * @param  array<string,mixed>  $details
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
                    'message' => $message,
                    'details' => $details,
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

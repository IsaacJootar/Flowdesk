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

class PaystackPayoutExecutionAdapter implements PayoutExecutionAdapterInterface
{
    public function providerKey(): string
    {
        return 'paystack';
    }

    public function executePayout(PayoutExecutionRequestData $request): PayoutExecutionResponseData
    {
        $secret = trim((string) config('execution.providers.paystack.secret_key', ''));
        $baseUrl = rtrim((string) config('execution.providers.paystack.base_url', 'https://api.paystack.co'), '/');

        if ($secret === '') {
            return $this->failed(
                code: 'paystack_not_configured',
                message: 'Paystack payout is not configured. Missing secret key.',
                retryable: false
            );
        }

        $recipientCode = $this->resolveRecipientCode($request, $secret, $baseUrl);
        if (($recipientCode['ok'] ?? false) !== true) {
            return $this->failed(
                code: (string) ($recipientCode['code'] ?? 'recipient_resolution_failed'),
                message: (string) ($recipientCode['message'] ?? 'Unable to resolve transfer recipient.'),
                retryable: (bool) ($recipientCode['retryable'] ?? false),
                details: (array) ($recipientCode['details'] ?? [])
            );
        }

        $amountKobo = (int) round($request->amount * 100);
        if ($amountKobo < 10) {
            return $this->failed(
                code: 'invalid_amount',
                message: 'Payout amount must be greater than zero.',
                retryable: false
            );
        }

        $payload = [
            'source' => 'balance',
            'amount' => $amountKobo,
            'recipient' => (string) $recipientCode['recipient_code'],
            'reason' => trim((string) ($request->narration ?? 'Flowdesk payout execution')),
            'currency' => strtoupper($request->currencyCode),
            'reference' => $request->idempotencyKey,
        ];

        try {
            $response = Http::timeout(25)
                ->withToken($secret)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/transfer', $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            if (! $response->successful() || ! ((bool) ($json['status'] ?? false))) {
                return $this->failed(
                    code: 'paystack_payout_request_failed',
                    message: (string) ($json['message'] ?? 'Paystack payout request failed.'),
                    retryable: $response->status() >= 500 || $response->status() === 429,
                    providerReference: (string) (($json['data']['reference'] ?? '') ?: $request->idempotencyKey),
                    details: [
                        'http_status' => $response->status(),
                        'response' => $json,
                    ]
                );
            }

            $providerReference = trim((string) (($json['data']['reference'] ?? '') ?: ($json['data']['transfer_code'] ?? '') ?: $request->idempotencyKey));
            $externalTransferId = trim((string) (($json['data']['transfer_code'] ?? '') ?: ($json['data']['id'] ?? '')));

            $providerStatus = strtolower(trim((string) ($json['data']['status'] ?? '')));
            $status = match (true) {
                in_array($providerStatus, ['success', 'successful'], true) => AdapterOperationStatus::Settled,
                in_array($providerStatus, ['failed'], true) => AdapterOperationStatus::Failed,
                in_array($providerStatus, ['reversed', 'reversal'], true) => AdapterOperationStatus::Reversed,
                in_array($providerStatus, ['otp', 'pending', 'received'], true) => AdapterOperationStatus::Processing,
                default => AdapterOperationStatus::Queued,
            };

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
                            code: 'paystack_payout_failed',
                            message: (string) ($json['message'] ?? 'Paystack payout failed.'),
                            retryable: true,
                            providerReference: $providerReference !== '' ? $providerReference : null,
                            details: ['response' => $json],
                        )
                        : null,
                ),
                externalTransferId: $externalTransferId !== '' ? $externalTransferId : ($providerReference !== '' ? $providerReference : null),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed(
                code: 'paystack_payout_exception',
                message: 'Paystack payout request threw an exception.',
                retryable: true,
                details: ['error' => $exception->getMessage()]
            );
        }
    }

    /**
     * @return array{ok:bool,recipient_code?:string,code?:string,message?:string,retryable?:bool,details?:array<string,mixed>}
     */
    private function resolveRecipientCode(PayoutExecutionRequestData $request, string $secret, string $baseUrl): array
    {
        $beneficiary = $request->beneficiary;
        $recipientCode = trim((string) ($beneficiary['recipient_code'] ?? $request->metadata['recipient_code'] ?? ''));

        if ($recipientCode !== '') {
            return ['ok' => true, 'recipient_code' => $recipientCode];
        }

        $accountNumber = trim((string) ($beneficiary['account_number'] ?? $request->metadata['account_number'] ?? ''));
        $bankCode = trim((string) ($beneficiary['bank_code'] ?? $request->metadata['bank_code'] ?? ''));
        $name = trim((string) ($beneficiary['name'] ?? $beneficiary['vendor_name'] ?? $request->metadata['beneficiary_name'] ?? 'Flowdesk Beneficiary'));

        if ($accountNumber === '' || $bankCode === '') {
            return [
                'ok' => false,
                'code' => 'beneficiary_data_missing',
                'message' => 'Missing recipient_code or beneficiary account_number + bank_code for Paystack payout.',
                'retryable' => false,
            ];
        }

        try {
            $response = Http::timeout(20)
                ->withToken($secret)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/transferrecipient', [
                    'type' => 'nuban',
                    'name' => $name,
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                    'currency' => strtoupper($request->currencyCode),
                ]);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            if (! $response->successful() || ! ((bool) ($json['status'] ?? false))) {
                return [
                    'ok' => false,
                    'code' => 'recipient_create_failed',
                    'message' => (string) ($json['message'] ?? 'Unable to create Paystack transfer recipient.'),
                    'retryable' => $response->status() >= 500 || $response->status() === 429,
                    'details' => [
                        'http_status' => $response->status(),
                        'response' => $json,
                    ],
                ];
            }

            $resolvedCode = trim((string) ($json['data']['recipient_code'] ?? ''));
            if ($resolvedCode === '') {
                return [
                    'ok' => false,
                    'code' => 'recipient_code_missing',
                    'message' => 'Paystack did not return recipient_code.',
                    'retryable' => false,
                    'details' => ['response' => $json],
                ];
            }

            return [
                'ok' => true,
                'recipient_code' => $resolvedCode,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'code' => 'recipient_create_exception',
                'message' => 'Exception while creating Paystack transfer recipient.',
                'retryable' => true,
                'details' => ['error' => $exception->getMessage()],
            ];
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

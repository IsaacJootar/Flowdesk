<?php

namespace App\Services\Execution\Adapters;

use App\Domains\Company\Models\Company;
use App\Services\Execution\Contracts\SubscriptionBillingAdapterInterface;
use App\Services\Execution\DTO\AdapterErrorData;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\SubscriptionBillingRequestData;
use App\Services\Execution\DTO\SubscriptionBillingResponseData;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaystackSubscriptionBillingAdapter implements SubscriptionBillingAdapterInterface
{
    public function providerKey(): string
    {
        return 'paystack';
    }

    public function billTenant(SubscriptionBillingRequestData $request): SubscriptionBillingResponseData
    {
        $secret = trim((string) config('execution.providers.paystack.secret_key', ''));
        $baseUrl = rtrim((string) config('execution.providers.paystack.base_url', 'https://api.paystack.co'), '/');

        if ($secret === '') {
            return $this->failed(
                code: 'paystack_not_configured',
                message: 'Paystack billing is not configured. Missing secret key.',
                retryable: false
            );
        }

        $customerEmail = $this->resolveCustomerEmail($request);
        if ($customerEmail === null) {
            return $this->failed(
                code: 'customer_email_missing',
                message: 'Billing requires customer email in metadata.customer_email or company email.',
                retryable: false
            );
        }

        $amountKobo = (int) round($request->amount * 100);
        if ($amountKobo < 1) {
            return $this->failed(
                code: 'invalid_amount',
                message: 'Billing amount must be greater than zero.',
                retryable: false
            );
        }

        $providerReference = $this->paystackSafeReference($request->idempotencyKey);

        $payload = [
            'email' => $customerEmail,
            'amount' => $amountKobo,
            'currency' => strtoupper($request->currencyCode),
            'reference' => $providerReference,
            'metadata' => array_merge($request->metadata, [
                'company_id' => $request->companyId,
                'subscription_id' => $request->subscriptionId,
                'plan_code' => $request->planCode,
                'period_start' => $request->periodStart->toDateString(),
                'period_end' => $request->periodEnd->toDateString(),
                'idempotency_key' => $request->idempotencyKey,
            ]),
        ];

        try {
            $response = Http::timeout(25)
                ->withToken($secret)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/transaction/initialize', $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            if (! $response->successful() || ! ((bool) ($json['status'] ?? false))) {
                return $this->failed(
                    code: 'paystack_billing_request_failed',
                    message: (string) ($json['message'] ?? 'Paystack billing request failed.'),
                    retryable: $response->status() >= 500 || $response->status() === 429,
                    providerReference: (string) (($json['data']['reference'] ?? '') ?: $providerReference),
                    details: [
                        'http_status' => $response->status(),
                        'response' => $json,
                    ]
                );
            }

            $providerReference = trim((string) (($json['data']['reference'] ?? '') ?: $providerReference));

            return new SubscriptionBillingResponseData(
                result: new AdapterOperationResult(
                    status: AdapterOperationStatus::Queued,
                    success: true,
                    providerReference: $providerReference !== '' ? $providerReference : null,
                    raw: [
                        'provider' => $this->providerKey(),
                        'message' => (string) ($json['message'] ?? 'Transaction initialized.'),
                        'authorization_url' => (string) ($json['data']['authorization_url'] ?? ''),
                        'access_code' => (string) ($json['data']['access_code'] ?? ''),
                        'response' => $json,
                    ],
                ),
                externalInvoiceId: $providerReference !== '' ? $providerReference : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed(
                code: 'paystack_billing_exception',
                message: 'Paystack billing request threw an exception.',
                retryable: true,
                details: ['error' => $exception->getMessage()]
            );
        }
    }

    private function resolveCustomerEmail(SubscriptionBillingRequestData $request): ?string
    {
        $metadataEmail = trim((string) (
            $request->metadata['customer_email']
            ?? $request->metadata['email']
            ?? ''
        ));

        if ($metadataEmail !== '') {
            return strtolower($metadataEmail);
        }

        $companyEmail = Company::query()
            ->whereKey($request->companyId)
            ->value('email');

        $companyEmail = trim((string) $companyEmail);

        return $companyEmail !== '' ? strtolower($companyEmail) : null;
    }

    private function paystackSafeReference(string $rawReference): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($rawReference)) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $seed = $rawReference !== '' ? $rawReference : uniqid('flowdesk-ref-', true);
            $normalized = 'flowdesk-'.substr(sha1($seed), 0, 24);
        }

        return substr($normalized, 0, 100);
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
    ): SubscriptionBillingResponseData {
        return new SubscriptionBillingResponseData(
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
            externalInvoiceId: null,
        );
    }
}

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

class FlutterwaveSubscriptionBillingAdapter implements SubscriptionBillingAdapterInterface
{
    public function providerKey(): string
    {
        return 'flutterwave';
    }

    public function billTenant(SubscriptionBillingRequestData $request): SubscriptionBillingResponseData
    {
        $secret = trim((string) config('execution.providers.flutterwave.secret_key', ''));
        $baseUrl = rtrim((string) config('execution.providers.flutterwave.base_url', 'https://api.flutterwave.com/v3'), '/');
        $redirectUrl = trim((string) config('execution.providers.flutterwave.redirect_url', ''));

        if ($secret === '') {
            return $this->failed(
                code: 'flutterwave_not_configured',
                message: 'Flutterwave billing is not configured. Missing secret key.',
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

        if ($redirectUrl === '') {
            return $this->failed(
                code: 'redirect_url_missing',
                message: 'Flutterwave billing requires configured redirect_url.',
                retryable: false
            );
        }

        $txRef = $request->idempotencyKey;

        $payload = [
            'tx_ref' => $txRef,
            'amount' => round($request->amount, 2),
            'currency' => strtoupper($request->currencyCode),
            'redirect_url' => $redirectUrl,
            'customer' => [
                'email' => $customerEmail,
                'name' => (string) ($request->metadata['customer_name'] ?? 'Flowdesk Tenant'),
            ],
            'customizations' => [
                'title' => 'Flowdesk Subscription Billing',
                'description' => sprintf(
                    'Plan %s (%s to %s)',
                    $request->planCode,
                    $request->periodStart->toDateString(),
                    $request->periodEnd->toDateString()
                ),
            ],
            'meta' => array_merge($request->metadata, [
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
                ->post($baseUrl.'/payments', $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            $providerStatus = strtolower((string) ($json['status'] ?? ''));
            $isSuccess = in_array($providerStatus, ['success', 'successful'], true);

            if (! $response->successful() || ! $isSuccess) {
                return $this->failed(
                    code: 'flutterwave_billing_request_failed',
                    message: (string) ($json['message'] ?? 'Flutterwave billing request failed.'),
                    retryable: $response->status() >= 500 || $response->status() === 429,
                    providerReference: (string) (($json['data']['tx_ref'] ?? '') ?: $txRef),
                    details: [
                        'http_status' => $response->status(),
                        'response' => $json,
                    ]
                );
            }

            $providerReference = trim((string) (($json['data']['tx_ref'] ?? '') ?: $txRef));
            $externalInvoiceId = trim((string) ($json['data']['id'] ?? $providerReference));

            return new SubscriptionBillingResponseData(
                result: new AdapterOperationResult(
                    status: AdapterOperationStatus::Queued,
                    success: true,
                    providerReference: $providerReference !== '' ? $providerReference : null,
                    raw: [
                        'provider' => $this->providerKey(),
                        'message' => (string) ($json['message'] ?? 'Payment created.'),
                        'payment_link' => (string) ($json['data']['link'] ?? ''),
                        'response' => $json,
                    ],
                ),
                externalInvoiceId: $externalInvoiceId !== '' ? $externalInvoiceId : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed(
                code: 'flutterwave_billing_exception',
                message: 'Flutterwave billing request threw an exception.',
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

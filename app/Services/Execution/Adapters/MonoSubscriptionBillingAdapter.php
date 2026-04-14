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

/**
 * Subscription billing adapter using Mono DirectPay.
 *
 * Mono DirectPay pulls payments directly from the tenant's bank account
 * via mandate authorization — no card needed, no card expiry issues.
 * This is more reliable than card-based billing for B2B SaaS in Nigeria.
 *
 * Flow:
 *   1. Flowdesk initiates a payment request via Mono DirectPay.
 *   2. Mono sends the tenant a bank authorization prompt (USSD or bank app).
 *   3. Tenant authorizes once; subsequent charges use the same mandate.
 *   4. Mono confirms settlement via webhook (mono.directpay.payment.paid).
 *
 * API reference: https://docs.mono.co/api/directpay
 * Auth header:   mono-sec-key: sk_live_...
 * Amounts:       kobo (integer), e.g. NGN 5,000 = 500000
 */
class MonoSubscriptionBillingAdapter implements SubscriptionBillingAdapterInterface
{
    public function providerKey(): string
    {
        return 'mono';
    }

    public function billTenant(SubscriptionBillingRequestData $request): SubscriptionBillingResponseData
    {
        $secretKey = trim((string) config('execution.providers.mono.secret_key', ''));
        $baseUrl   = rtrim((string) config('execution.providers.mono.base_url', 'https://api.withmono.com'), '/');

        if ($secretKey === '') {
            return $this->failed(
                code: 'mono_not_configured',
                message: 'Mono billing is not configured. Missing secret key.',
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

        // DirectPay uses kobo (minor units), consistent with Mono Connect balance format
        $amountKobo = (int) round($request->amount * 100);
        if ($amountKobo < 1) {
            return $this->failed(
                code: 'invalid_amount',
                message: 'Billing amount must be greater than zero.',
                retryable: false
            );
        }

        // Build a safe reference — Mono references must be alphanumeric with hyphens only
        $reference = $this->monoSafeReference($request->idempotencyKey);

        $payload = [
            'amount'      => $amountKobo,
            'type'        => 'onetime-debit',
            'description' => sprintf(
                'Flowdesk %s plan — %s to %s',
                ucfirst($request->planCode),
                $request->periodStart->toDateString(),
                $request->periodEnd->toDateString()
            ),
            'reference'   => $reference,
            'redirect_url' => (string) config('execution.providers.mono.redirect_url', ''),
            'customer'    => [
                'email' => $customerEmail,
            ],
            'meta' => array_merge($request->metadata, [
                'company_id'       => $request->companyId,
                'subscription_id'  => $request->subscriptionId,
                'plan_code'        => $request->planCode,
                'period_start'     => $request->periodStart->toDateString(),
                'period_end'       => $request->periodEnd->toDateString(),
                'idempotency_key'  => $request->idempotencyKey,
            ]),
        ];

        try {
            $response = Http::timeout(25)
                ->withHeaders(['mono-sec-key' => $secretKey])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl . '/v1/payments/initiate', $payload);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            $responseStatus = strtolower(trim((string) ($json['status'] ?? '')));
            $accepted = in_array($responseStatus, ['successful', 'success'], true);

            if (! $response->successful() || ! $accepted) {
                return $this->failed(
                    code: 'mono_billing_request_failed',
                    message: (string) ($json['message'] ?? 'Mono billing request failed.'),
                    retryable: $response->status() >= 500 || $response->status() === 429,
                    providerReference: $reference,
                    details: ['http_status' => $response->status(), 'response' => $json]
                );
            }

            $data        = is_array($json['data'] ?? null) ? (array) $json['data'] : [];
            $providerRef = trim((string) ($data['reference'] ?? $reference));

            return new SubscriptionBillingResponseData(
                result: new AdapterOperationResult(
                    // DirectPay payments are Queued until the tenant authorizes and Mono confirms via webhook
                    status: AdapterOperationStatus::Queued,
                    success: true,
                    providerReference: $providerRef !== '' ? $providerRef : null,
                    raw: [
                        'provider'      => $this->providerKey(),
                        'message'       => (string) ($json['message'] ?? 'DirectPay payment initiated.'),
                        'payment_url'   => (string) ($data['payment_url']   ?? $data['mono_url']   ?? ''),
                        'checkout_url'  => (string) ($data['checkout_url']  ?? ''),
                        'response'      => $json,
                    ],
                ),
                externalInvoiceId: $providerRef !== '' ? $providerRef : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed(
                code: 'mono_billing_exception',
                message: 'Mono billing request threw an exception.',
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

    private function monoSafeReference(string $rawReference): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9-]+/', '-', trim($rawReference)) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $seed       = $rawReference !== '' ? $rawReference : uniqid('flowdesk-ref-', true);
            $normalized = 'flowdesk-' . substr(sha1($seed), 0, 24);
        }

        return substr($normalized, 0, 100);
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
    ): SubscriptionBillingResponseData {
        return new SubscriptionBillingResponseData(
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
            externalInvoiceId: null,
        );
    }
}

<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\TenantBillingLedgerEntry;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Services\Execution\DTO\AdapterOperationResult;
use App\Services\Execution\DTO\AdapterOperationStatus;
use App\Services\Execution\DTO\SubscriptionBillingRequestData;
use App\Services\TenantAuditLogger;
use App\Services\TenantBillingAutomationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionBillingAttemptProcessor
{
    public function __construct(
        private readonly TenantExecutionAdapterFactory $adapterFactory,
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly TenantBillingAutomationService $billingAutomationService,
    ) {
    }

    /**
     * Process one queued billing attempt through the configured provider adapter.
     */
    public function processAttemptById(int $attemptId): bool
    {
        $attempt = TenantSubscriptionBillingAttempt::query()
            ->with(['subscription.company'])
            ->find($attemptId);

        if (! $attempt) {
            return false;
        }

        if (in_array((string) $attempt->attempt_status, ['settled', 'reversed'], true)) {
            return false;
        }

        $subscription = $attempt->subscription;
        $company = $subscription?->company;
        if (! $subscription || ! $company) {
            return false;
        }

        $attempt->forceFill([
            'attempt_status' => 'processing',
            'processed_at' => now(),
            // Retry flows can re-run the same row while preserving idempotency key.
            'attempt_count' => max(1, (int) $attempt->attempt_count) + ((string) $attempt->attempt_status === 'queued' ? 0 : 1),
        ])->save();

        $adapter = $this->adapterFactory->billingAdapterForSubscription($subscription);

        $response = $adapter->billTenant(new SubscriptionBillingRequestData(
            companyId: (int) $attempt->company_id,
            subscriptionId: (int) $attempt->tenant_subscription_id,
            planCode: (string) $subscription->plan_code,
            amount: (float) $attempt->amount,
            currencyCode: strtoupper((string) $attempt->currency_code),
            periodStart: CarbonImmutable::parse((string) $attempt->period_start),
            periodEnd: CarbonImmutable::parse((string) $attempt->period_end),
            idempotencyKey: (string) $attempt->idempotency_key,
            metadata: [
                'billing_attempt_id' => (int) $attempt->id,
            ],
        ));

        $this->applyAdapterResult($attempt, $response->result, $response->externalInvoiceId);

        if ((string) $attempt->attempt_status === 'settled') {
            $this->ensureLedgerChargeEntry($attempt);
            $this->billingAutomationService->evaluateCompany($company);
        }

        return true;
    }

    /**
     * @return array{processed:int}
     */
    public function processQueued(?int $companyId = null, int $batchSize = 200): array
    {
        $processed = 0;

        TenantSubscriptionBillingAttempt::query()
            ->whereIn('attempt_status', ['queued'])
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->orderBy('id')
            ->limit(max(1, $batchSize))
            ->get()
            ->each(function (TenantSubscriptionBillingAttempt $attempt) use (&$processed): void {
                if ($this->processAttemptById((int) $attempt->id)) {
                    $processed++;
                }
            });

        return ['processed' => $processed];
    }

    public function markFromWebhook(TenantSubscriptionBillingAttempt $attempt, string $nextStatus, ?string $eventId, array $normalizedPayload = []): void
    {
        $attempt->forceFill([
            'attempt_status' => $nextStatus,
            'last_provider_event_id' => $eventId,
            'provider_response' => $normalizedPayload,
            'processed_at' => now(),
            'settled_at' => $nextStatus === 'settled' ? now() : $attempt->settled_at,
            'failed_at' => $nextStatus === 'failed' ? now() : $attempt->failed_at,
            'updated_by' => null,
        ])->save();

        if ($nextStatus === 'settled') {
            $this->ensureLedgerChargeEntry($attempt);
            $company = $attempt->subscription?->company;
            if ($company) {
                $this->billingAutomationService->evaluateCompany($company);
            }
        }
    }

    private function applyAdapterResult(TenantSubscriptionBillingAttempt $attempt, AdapterOperationResult $result, ?string $externalInvoiceId): void
    {
        $status = match ($result->status) {
            AdapterOperationStatus::Settled => 'settled',
            AdapterOperationStatus::Failed => 'failed',
            AdapterOperationStatus::Reversed => 'reversed',
            AdapterOperationStatus::Skipped => 'skipped',
            AdapterOperationStatus::Queued, AdapterOperationStatus::Processing => 'webhook_pending',
        };

        $attempt->forceFill([
            'attempt_status' => $status,
            'external_invoice_id' => $externalInvoiceId ?: $attempt->external_invoice_id,
            'provider_reference' => $result->providerReference ?: $attempt->provider_reference,
            'provider_response' => $result->raw,
            'error_code' => $result->error?->code,
            'error_message' => $result->error?->message,
            'processed_at' => now(),
            'settled_at' => $status === 'settled' ? now() : $attempt->settled_at,
            'failed_at' => $status === 'failed' ? now() : $attempt->failed_at,
        ])->save();

        $this->tenantAuditLogger->log(
            companyId: (int) $attempt->company_id,
            action: 'tenant.billing.auto_charge_'.(string) $status,
            actor: null,
            description: 'Subscription billing attempt processed.',
            entityType: TenantSubscriptionBillingAttempt::class,
            entityId: (int) $attempt->id,
            metadata: [
                'provider' => (string) $attempt->provider_key,
                'status' => $status,
            ],
        );
    }

    private function ensureLedgerChargeEntry(TenantSubscriptionBillingAttempt $attempt): void
    {
        $exists = TenantBillingLedgerEntry::query()
            ->where('company_id', (int) $attempt->company_id)
            ->where('source_type', TenantSubscriptionBillingAttempt::class)
            ->where('source_id', (int) $attempt->id)
            ->exists();

        if ($exists) {
            return;
        }

        TenantBillingLedgerEntry::query()->create([
            'company_id' => (int) $attempt->company_id,
            'tenant_subscription_id' => (int) $attempt->tenant_subscription_id,
            'source_type' => TenantSubscriptionBillingAttempt::class,
            'source_id' => (int) $attempt->id,
            'entry_type' => 'charge',
            'direction' => 'debit',
            'amount' => (float) $attempt->amount,
            'currency_code' => strtoupper((string) $attempt->currency_code),
            'effective_date' => now()->toDateString(),
            'period_start' => $attempt->period_start?->toDateString(),
            'period_end' => $attempt->period_end?->toDateString(),
            'description' => 'Automated subscription billing charge',
            'metadata' => [
                'provider_key' => (string) $attempt->provider_key,
                'provider_reference' => (string) ($attempt->provider_reference ?? ''),
                'external_invoice_id' => (string) ($attempt->external_invoice_id ?? ''),
            ],
        ]);
    }
}
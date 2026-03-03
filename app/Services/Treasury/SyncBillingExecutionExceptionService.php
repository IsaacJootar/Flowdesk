<?php

namespace App\Services\Treasury;

use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Services\TenantAuditLogger;

class SyncBillingExecutionExceptionService
{
    public function __construct(
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    public function syncForAttempt(TenantSubscriptionBillingAttempt $attempt, string $source = 'execution'): void
    {
        $status = (string) $attempt->attempt_status;
        if (! in_array($status, ['failed', 'reversed', 'settled'], true)) {
            return;
        }

        if ($status === 'settled') {
            $this->autoResolveOpenHandoffExceptions($attempt, 'Auto-closed after billing moved to settled.');

            return;
        }

        $attempt->loadMissing('subscription:id,company_id,plan_code');

        $currentCode = $status === 'reversed'
            ? 'execution_billing_reversed'
            : 'execution_billing_failed';

        // Keep one active billing handoff row per attempt so treasury sees latest execution truth.
        $this->closeSupersededOpenHandoffExceptions($attempt, $currentCode);

        $openCurrent = $this->openExceptionForAttempt((int) $attempt->company_id, (int) $attempt->id, $currentCode);
        if ($openCurrent instanceof ReconciliationException && (string) data_get((array) ($openCurrent->metadata ?? []), 'attempt_status') === $status) {
            return;
        }

        $severity = $status === 'reversed'
            ? ReconciliationException::SEVERITY_CRITICAL
            : ReconciliationException::SEVERITY_HIGH;

        $baseMetadata = [
            'billing_attempt_id' => (int) $attempt->id,
            'tenant_subscription_id' => (int) $attempt->tenant_subscription_id,
            'plan_code' => (string) ($attempt->subscription?->plan_code ?? ''),
            'provider_key' => (string) ($attempt->provider_key ?? ''),
            'provider_reference' => (string) ($attempt->provider_reference ?? ''),
            'external_invoice_id' => (string) ($attempt->external_invoice_id ?? ''),
            'error_code' => (string) ($attempt->error_code ?? ''),
            'attempt_status' => $status,
            'source' => $source,
            'auto_created' => true,
        ];

        $exception = $openCurrent ?: new ReconciliationException();

        $exception->forceFill([
            'company_id' => (int) $attempt->company_id,
            'bank_statement_line_id' => null,
            'reconciliation_match_id' => null,
            'exception_code' => $currentCode,
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => $severity,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => $this->nextActionForStatus($status),
            'details' => $this->detailsForStatus($attempt, $status),
            'metadata' => array_merge((array) ($openCurrent?->metadata ?? []), $baseMetadata),
            'updated_by' => $attempt->updated_by,
        ]);

        if (! $exception->exists) {
            $exception->created_by = $attempt->updated_by ?? $attempt->created_by;
        }

        $exception->save();

        $event = $this->tenantAuditLogger->log(
            companyId: (int) $attempt->company_id,
            action: 'tenant.execution.billing.handoff_to_treasury',
            actor: null,
            description: 'Execution billing exception handed off to treasury queue.',
            entityType: TenantSubscriptionBillingAttempt::class,
            entityId: (int) $attempt->id,
            metadata: [
                'billing_attempt_id' => (int) $attempt->id,
                'tenant_subscription_id' => (int) $attempt->tenant_subscription_id,
                'attempt_status' => $status,
                'source' => $source,
                'reconciliation_exception_id' => (int) $exception->id,
                'reconciliation_exception_code' => $currentCode,
            ],
        );

        $metadata = (array) ($exception->metadata ?? []);
        $metadata['execution_incident_event_id'] = (int) $event->id;
        $metadata['execution_incident_id'] = $this->formatIncidentId((int) $event->id);
        $metadata['execution_incident_action'] = (string) $event->action;
        $exception->forceFill([
            'metadata' => $metadata,
            'updated_by' => $attempt->updated_by,
        ])->save();
    }

    private function closeSupersededOpenHandoffExceptions(TenantSubscriptionBillingAttempt $attempt, string $activeCode): void
    {
        foreach ($this->openHandoffExceptionsForAttempt((int) $attempt->company_id, (int) $attempt->id) as $exception) {
            if ((string) $exception->exception_code === $activeCode) {
                continue;
            }

            $exception->forceFill([
                'exception_status' => ReconciliationException::STATUS_RESOLVED,
                'resolution_notes' => 'Superseded by latest billing execution status.',
                'resolved_at' => now(),
                'resolved_by_user_id' => null,
                'updated_by' => $attempt->updated_by,
            ])->save();
        }
    }

    private function autoResolveOpenHandoffExceptions(TenantSubscriptionBillingAttempt $attempt, string $notes): void
    {
        foreach ($this->openHandoffExceptionsForAttempt((int) $attempt->company_id, (int) $attempt->id) as $exception) {
            $exception->forceFill([
                'exception_status' => ReconciliationException::STATUS_RESOLVED,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'resolved_by_user_id' => null,
                'updated_by' => $attempt->updated_by,
            ])->save();
        }
    }

    /**
     * @return array<int, ReconciliationException>
     */
    private function openHandoffExceptionsForAttempt(int $companyId, int $attemptId): array
    {
        return ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->whereIn('exception_code', ['execution_billing_failed', 'execution_billing_reversed'])
            ->get()
            ->filter(fn (ReconciliationException $row): bool => (int) data_get((array) ($row->metadata ?? []), 'billing_attempt_id', 0) === $attemptId)
            ->values()
            ->all();
    }

    private function openExceptionForAttempt(int $companyId, int $attemptId, string $code): ?ReconciliationException
    {
        return ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('exception_code', $code)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->get()
            ->first(fn (ReconciliationException $row): bool => (int) data_get((array) ($row->metadata ?? []), 'billing_attempt_id', 0) === $attemptId);
    }

    private function detailsForStatus(TenantSubscriptionBillingAttempt $attempt, string $status): string
    {
        $subscriptionId = (int) $attempt->tenant_subscription_id;
        $providerRef = (string) ($attempt->provider_reference ?? 'n/a');
        $errorCode = (string) ($attempt->error_code ?? 'n/a');

        return $status === 'reversed'
            ? sprintf('Subscription billing execution was reversed for subscription %d (provider ref %s).', $subscriptionId, $providerRef)
            : sprintf('Subscription billing execution failed for subscription %d (provider ref %s, error %s).', $subscriptionId, $providerRef, $errorCode);
    }

    private function nextActionForStatus(string $status): string
    {
        return $status === 'reversed'
            ? 'Review reversal context before re-running billing charge.'
            : 'Check provider/configuration and retry billing from execution tools.';
    }

    private function formatIncidentId(int $eventId): string
    {
        return 'EXE-'.str_pad((string) max(0, $eventId), 6, '0', STR_PAD_LEFT);
    }
}

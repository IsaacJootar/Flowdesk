<?php

namespace App\Services\Treasury;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Services\TenantAuditLogger;

class SyncWebhookExecutionExceptionService
{
    public function __construct(
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    public function syncForEvent(ExecutionWebhookEvent $event, string $source = 'execution'): void
    {
        $companyId = (int) ($event->company_id ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $status = (string) ($event->processing_status ?? '');
        if ($status !== 'failed') {
            $this->autoResolveOpenHandoffExceptions($event, 'Auto-closed after webhook event left failed state.');

            return;
        }

        $currentCode = 'execution_webhook_reconcile_failed';
        $openCurrent = $this->openExceptionForEvent($companyId, (int) $event->id, $currentCode);

        $reason = (string) ($event->failure_reason ?? 'Webhook reconciliation failed.');
        if (
            $openCurrent instanceof ReconciliationException
            && (string) data_get((array) ($openCurrent->metadata ?? []), 'processing_status') === $status
            && (string) data_get((array) ($openCurrent->metadata ?? []), 'failure_reason') === $reason
        ) {
            return;
        }

        $baseMetadata = [
            'execution_webhook_event_id' => (int) $event->id,
            'provider_key' => (string) ($event->provider_key ?? ''),
            'external_event_id' => (string) ($event->external_event_id ?? ''),
            'event_type' => (string) ($event->event_type ?? ''),
            'verification_status' => (string) ($event->verification_status ?? ''),
            'processing_status' => $status,
            'failure_reason' => $reason,
            'tenant_subscription_billing_attempt_id' => (int) ($event->tenant_subscription_billing_attempt_id ?? 0),
            'request_payout_execution_attempt_id' => (int) ($event->request_payout_execution_attempt_id ?? 0),
            'source' => $source,
            'auto_created' => true,
        ];

        $exception = $openCurrent ?: new ReconciliationException();

        $exception->forceFill([
            'company_id' => $companyId,
            'bank_statement_line_id' => null,
            'reconciliation_match_id' => null,
            'exception_code' => $currentCode,
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => $this->severityForEvent($event),
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => 'Review webhook payload/signature mapping and retry reconciliation from execution tools.',
            'details' => $this->detailsForEvent($event, $reason),
            'metadata' => array_merge((array) ($openCurrent?->metadata ?? []), $baseMetadata),
            'updated_by' => null,
        ]);

        if (! $exception->exists) {
            $exception->created_by = null;
        }

        $exception->save();

        $auditEvent = $this->tenantAuditLogger->log(
            companyId: $companyId,
            action: 'tenant.execution.webhook.handoff_to_treasury',
            actor: null,
            description: 'Execution webhook reconciliation failure handed off to treasury queue.',
            entityType: ExecutionWebhookEvent::class,
            entityId: (int) $event->id,
            metadata: [
                'execution_webhook_event_id' => (int) $event->id,
                'provider_key' => (string) ($event->provider_key ?? ''),
                'event_type' => (string) ($event->event_type ?? ''),
                'processing_status' => $status,
                'failure_reason' => $reason,
                'source' => $source,
                'reconciliation_exception_id' => (int) $exception->id,
                'reconciliation_exception_code' => $currentCode,
            ],
        );

        $metadata = (array) ($exception->metadata ?? []);
        $metadata['execution_incident_event_id'] = (int) $auditEvent->id;
        $metadata['execution_incident_id'] = $this->formatIncidentId((int) $auditEvent->id);
        $metadata['execution_incident_action'] = (string) $auditEvent->action;

        $exception->forceFill([
            'metadata' => $metadata,
            'updated_by' => null,
        ])->save();
    }

    private function autoResolveOpenHandoffExceptions(ExecutionWebhookEvent $event, string $notes): void
    {
        foreach ($this->openHandoffExceptionsForEvent((int) ($event->company_id ?? 0), (int) $event->id) as $exception) {
            $exception->forceFill([
                'exception_status' => ReconciliationException::STATUS_RESOLVED,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'resolved_by_user_id' => null,
                'updated_by' => null,
            ])->save();
        }
    }

    /**
     * @return array<int, ReconciliationException>
     */
    private function openHandoffExceptionsForEvent(int $companyId, int $eventId): array
    {
        if ($companyId <= 0 || $eventId <= 0) {
            return [];
        }

        return ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->where('exception_code', 'execution_webhook_reconcile_failed')
            ->get()
            ->filter(fn (ReconciliationException $row): bool => (int) data_get((array) ($row->metadata ?? []), 'execution_webhook_event_id', 0) === $eventId)
            ->values()
            ->all();
    }

    private function openExceptionForEvent(int $companyId, int $eventId, string $code): ?ReconciliationException
    {
        return ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('exception_code', $code)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->get()
            ->first(fn (ReconciliationException $row): bool => (int) data_get((array) ($row->metadata ?? []), 'execution_webhook_event_id', 0) === $eventId);
    }

    private function severityForEvent(ExecutionWebhookEvent $event): string
    {
        if ((string) ($event->verification_status ?? '') !== 'valid') {
            return ReconciliationException::SEVERITY_CRITICAL;
        }

        return ReconciliationException::SEVERITY_HIGH;
    }

    private function detailsForEvent(ExecutionWebhookEvent $event, string $reason): string
    {
        return sprintf(
            'Webhook reconciliation failed for provider %s (event %s, type %s). Reason: %s',
            (string) ($event->provider_key ?? 'n/a'),
            (string) ($event->external_event_id ?? 'n/a'),
            (string) ($event->event_type ?? 'n/a'),
            $reason,
        );
    }

    private function formatIncidentId(int $eventId): string
    {
        return 'EXE-'.str_pad((string) max(0, $eventId), 6, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Services\Treasury;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Services\TenantAuditLogger;

class SyncPayoutExecutionExceptionService
{
    public function __construct(
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    public function syncForAttempt(RequestPayoutExecutionAttempt $attempt, string $source = 'execution'): void
    {
        $status = (string) $attempt->execution_status;
        if (! in_array($status, ['failed', 'reversed', 'settled'], true)) {
            return;
        }

        if ($status === 'settled') {
            $this->autoResolveOpenHandoffExceptions($attempt, 'Auto-closed after payout moved to settled.');

            return;
        }

        $attempt->loadMissing('request:id,request_code,title');

        $currentCode = $status === 'reversed'
            ? 'execution_payout_reversed'
            : 'execution_payout_failed';

        // Keep only one active handoff exception per payout attempt so queue operators see the latest truth.
        $this->closeSupersededOpenHandoffExceptions($attempt, $currentCode);

        $openCurrent = $this->openExceptionForAttempt((int) $attempt->company_id, (int) $attempt->id, $currentCode);
        if ($openCurrent instanceof ReconciliationException && (string) data_get((array) ($openCurrent->metadata ?? []), 'execution_status') === $status) {
            return;
        }

        $details = $this->detailsForStatus($attempt, $status);
        $nextAction = $this->nextActionForStatus($status);
        $severity = $status === 'reversed'
            ? ReconciliationException::SEVERITY_CRITICAL
            : ReconciliationException::SEVERITY_HIGH;

        $baseMetadata = [
            'payout_attempt_id' => (int) $attempt->id,
            'request_id' => (int) $attempt->request_id,
            'request_code' => (string) ($attempt->request?->request_code ?? ''),
            'provider_key' => (string) ($attempt->provider_key ?? ''),
            'provider_reference' => (string) ($attempt->provider_reference ?? ''),
            'external_transfer_id' => (string) ($attempt->external_transfer_id ?? ''),
            'error_code' => (string) ($attempt->error_code ?? ''),
            'execution_status' => $status,
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
            'next_action' => $nextAction,
            'details' => $details,
            'metadata' => array_merge((array) ($openCurrent?->metadata ?? []), $baseMetadata),
            'updated_by' => $attempt->updated_by,
        ]);

        if (! $exception->exists) {
            $exception->created_by = $attempt->updated_by ?? $attempt->created_by;
        }

        $exception->save();

        $event = $this->tenantAuditLogger->log(
            companyId: (int) $attempt->company_id,
            action: 'tenant.execution.payout.handoff_to_treasury',
            actor: null,
            description: 'Execution payout exception handed off to treasury queue.',
            entityType: RequestPayoutExecutionAttempt::class,
            entityId: (int) $attempt->id,
            metadata: [
                'payout_attempt_id' => (int) $attempt->id,
                'request_id' => (int) $attempt->request_id,
                'request_code' => (string) ($attempt->request?->request_code ?? ''),
                'execution_status' => $status,
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

    private function closeSupersededOpenHandoffExceptions(RequestPayoutExecutionAttempt $attempt, string $activeCode): void
    {
        foreach ($this->openHandoffExceptionsForAttempt((int) $attempt->company_id, (int) $attempt->id) as $exception) {
            if ((string) $exception->exception_code === $activeCode) {
                continue;
            }

            $exception->forceFill([
                'exception_status' => ReconciliationException::STATUS_RESOLVED,
                'resolution_notes' => 'Superseded by latest payout execution status.',
                'resolved_at' => now(),
                'resolved_by_user_id' => null,
                'updated_by' => $attempt->updated_by,
            ])->save();
        }
    }

    private function autoResolveOpenHandoffExceptions(RequestPayoutExecutionAttempt $attempt, string $notes): void
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
            ->whereIn('exception_code', ['execution_payout_failed', 'execution_payout_reversed'])
            ->get()
            ->filter(fn (ReconciliationException $row): bool => (int) data_get((array) ($row->metadata ?? []), 'payout_attempt_id', 0) === $attemptId)
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
            ->first(fn (ReconciliationException $row): bool => (int) data_get((array) ($row->metadata ?? []), 'payout_attempt_id', 0) === $attemptId);
    }

    private function detailsForStatus(RequestPayoutExecutionAttempt $attempt, string $status): string
    {
        $requestCode = (string) ($attempt->request?->request_code ?? 'N/A');
        $providerRef = (string) ($attempt->provider_reference ?? 'n/a');
        $errorCode = (string) ($attempt->error_code ?? 'n/a');

        return $status === 'reversed'
            ? sprintf('Payout execution was reversed for request %s (provider ref %s).', $requestCode, $providerRef)
            : sprintf('Payout execution failed for request %s (provider ref %s, error %s).', $requestCode, $providerRef, $errorCode);
    }

    private function nextActionForStatus(string $status): string
    {
        return $status === 'reversed'
            ? 'Review reversal incident in execution context before retrying payout.'
            : 'Check provider/configuration and retry payout from execution tools.';
    }

    private function formatIncidentId(int $eventId): string
    {
        return 'EXE-'.str_pad((string) max(0, $eventId), 6, '0', STR_PAD_LEFT);
    }
}

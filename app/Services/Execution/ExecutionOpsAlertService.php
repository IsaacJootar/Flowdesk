<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Services\Procurement\ProcurementControlSettingsService;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\TreasuryControlSettingsService;
use App\Services\Execution\ExecutionAlertChannelDeliveryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ExecutionOpsAlertService
{
    public function __construct(
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly ProcurementControlSettingsService $procurementControlSettingsService,
        private readonly TreasuryControlSettingsService $treasuryControlSettingsService,
        private readonly ExecutionAlertChannelDeliveryService $executionAlertChannelDeliveryService,
    ) {
    }

    /**
     * @return array{window_minutes:int,threshold:int,alerts:array<int,array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}>}
     */
    public function emitWarnings(?int $windowMinutes = null): array
    {
        $windowMinutes = $windowMinutes ?? (int) config('execution.ops_alerts.window_minutes', 60);
        $threshold = max(1, (int) config('execution.ops_alerts.failure_threshold', 5));

        $summary = $this->summarizeFailures($windowMinutes, $threshold);

        foreach ($summary['alerts'] as $alert) {
            Log::warning('Execution operations center alert triggered.', [
                'type' => $alert['type'],
                'pipeline' => $alert['pipeline'],
                'provider' => $alert['provider'],
                'company_id' => $alert['company_id'],
                'count' => $alert['count'],
                'window_minutes' => $summary['window_minutes'],
                'threshold' => $alert['threshold'],
                'context' => (array) ($alert['context'] ?? []),
            ]);

            // Persist alert summaries so operators can review tenant-specific incidents from UI.
            $this->logAlertSummary($alert, (int) $summary['window_minutes']);
            // Delivery uses tenant communication settings so alerts are not log-only for tenant operators.
            $this->executionAlertChannelDeliveryService->deliver($alert, (int) $summary['window_minutes']);
        }

        return $summary;
    }

    /**
     * @return array{window_minutes:int,threshold:int,alerts:array<int,array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}>}
     */
    public function summarizeFailures(int $windowMinutes, int $threshold): array
    {
        $windowMinutes = max(1, $windowMinutes);
        $threshold = max(1, $threshold);
        $since = Carbon::now()->subMinutes($windowMinutes);

        $alerts = [];

        // Group by provider + tenant company so platform ops can identify hotspot combinations.
        $billingFailures = TenantSubscriptionBillingAttempt::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('attempt_status', 'failed')
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($billingFailures as $row) {
            if ((int) $row->aggregate_count < $threshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'failure_spike',
                'pipeline' => 'billing',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $threshold,
            ];
        }

        $payoutFailures = RequestPayoutExecutionAttempt::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('execution_status', 'failed')
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($payoutFailures as $row) {
            if ((int) $row->aggregate_count < $threshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'failure_spike',
                'pipeline' => 'payout',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $threshold,
            ];
        }

        $webhookFailures = ExecutionWebhookEvent::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where(function ($query): void {
                $query->where('processing_status', 'failed')
                    ->orWhere('verification_status', 'invalid');
            })
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($webhookFailures as $row) {
            if ((int) $row->aggregate_count < $threshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'failure_spike',
                'pipeline' => 'webhook',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $threshold,
            ];
        }

        // Stuck-queue alerts catch silent backlog growth even when failure counts look normal.
        $stuckOlderThan = max(1, (int) config('execution.ops_alerts.stuck_queued_older_than_minutes', 45));
        $stuckThreshold = max(1, (int) config('execution.ops_alerts.stuck_queued_threshold', 10));
        $stuckCutoff = Carbon::now()->subMinutes($stuckOlderThan);

        $billingStuck = TenantSubscriptionBillingAttempt::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('attempt_status', 'queued')
            ->where('queued_at', '<=', $stuckCutoff)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($billingStuck as $row) {
            if ((int) $row->aggregate_count < $stuckThreshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'stuck_queued',
                'pipeline' => 'billing',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $stuckThreshold,
            ];
        }

        $payoutStuck = RequestPayoutExecutionAttempt::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('execution_status', 'queued')
            ->where('queued_at', '<=', $stuckCutoff)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($payoutStuck as $row) {
            if ((int) $row->aggregate_count < $stuckThreshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'stuck_queued',
                'pipeline' => 'payout',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $stuckThreshold,
            ];
        }

        $webhookStuck = ExecutionWebhookEvent::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('processing_status', 'queued')
            ->where('received_at', '<=', $stuckCutoff)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($webhookStuck as $row) {
            if ((int) $row->aggregate_count < $stuckThreshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'stuck_queued',
                'pipeline' => 'webhook',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $stuckThreshold,
            ];
        }

        // Invalid webhook spikes usually point to signature config drift or payload contract changes.
        $invalidWebhookThreshold = max(1, (int) config('execution.ops_alerts.invalid_webhook_threshold', 5));
        $invalidWebhookSpikes = ExecutionWebhookEvent::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('verification_status', 'invalid')
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($invalidWebhookSpikes as $row) {
            if ((int) $row->aggregate_count < $invalidWebhookThreshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'invalid_webhook_spike',
                'pipeline' => 'webhook',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
                'threshold' => $invalidWebhookThreshold,
            ];
        }

        $alerts = array_merge($alerts, $this->summarizeStaleCommitmentAlerts());
        $alerts = array_merge($alerts, $this->summarizeReconciliationBacklogAlerts());

        return [
            'window_minutes' => $windowMinutes,
            'threshold' => $threshold,
            'alerts' => $alerts,
        ];
    }

    /**
     * @return array<int,array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context:array{age_hours:int}}>
     */
    private function summarizeStaleCommitmentAlerts(): array
    {
        $alerts = [];

        $companyIds = ProcurementCommitment::query()
            ->where('commitment_status', ProcurementCommitment::STATUS_ACTIVE)
            ->select('company_id')
            ->distinct()
            ->pluck('company_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        foreach ($companyIds as $companyId) {
            $controls = $this->procurementControlSettingsService->effectiveControls($companyId);

            $ageHours = max(1, (int) ($controls['stale_commitment_alert_age_hours'] ?? 72));
            $countThreshold = max(1, (int) ($controls['stale_commitment_alert_count_threshold'] ?? 3));
            $cutoff = Carbon::now()->subHours($ageHours);

            $count = (int) ProcurementCommitment::query()
                ->where('company_id', $companyId)
                ->where('commitment_status', ProcurementCommitment::STATUS_ACTIVE)
                ->where('effective_at', '<=', $cutoff)
                ->count();

            if ($count < $countThreshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'stale_commitment',
                'pipeline' => 'procurement',
                'provider' => 'system',
                'company_id' => $companyId,
                'count' => $count,
                'threshold' => $countThreshold,
                'context' => [
                    'age_hours' => $ageHours,
                ],
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int,array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context:array{age_hours:int}}>
     */
    private function summarizeReconciliationBacklogAlerts(): array
    {
        $alerts = [];

        $companyIds = ReconciliationException::query()
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->select('company_id')
            ->distinct()
            ->pluck('company_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        foreach ($companyIds as $companyId) {
            $controls = $this->treasuryControlSettingsService->effectiveControls($companyId);

            $ageHours = max(1, (int) ($controls['exception_alert_age_hours'] ?? 48));
            $countThreshold = max(1, (int) ($controls['reconciliation_backlog_alert_count_threshold'] ?? 5));
            $cutoff = Carbon::now()->subHours($ageHours);

            $count = (int) ReconciliationException::query()
                ->where('company_id', $companyId)
                ->where('exception_status', ReconciliationException::STATUS_OPEN)
                ->where('created_at', '<=', $cutoff)
                ->count();

            if ($count < $countThreshold) {
                continue;
            }

            $alerts[] = [
                'type' => 'reconciliation_backlog',
                'pipeline' => 'treasury',
                'provider' => 'system',
                'company_id' => $companyId,
                'count' => $count,
                'threshold' => $countThreshold,
                'context' => [
                    'age_hours' => $ageHours,
                ],
            ];
        }

        return $alerts;
    }

    /**
     * @param  array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}  $alert
     */
    private function logAlertSummary(array $alert, int $windowMinutes): void
    {
        $companyId = (int) ($alert['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $alertType = (string) ($alert['type'] ?? '');
        $metadata = [
            'type' => $alertType,
            'pipeline' => (string) ($alert['pipeline'] ?? ''),
            'provider_key' => (string) ($alert['provider'] ?? ''),
            'count' => (int) ($alert['count'] ?? 0),
            'threshold' => (int) ($alert['threshold'] ?? 0),
            'window_minutes' => in_array($alertType, ['failure_spike', 'stuck_queued', 'invalid_webhook_spike'], true)
                ? $windowMinutes
                : 0,
            'trigger' => 'execution:ops:alert-summary',
        ];

        $context = (array) ($alert['context'] ?? []);
        if ($context !== []) {
            $metadata = array_merge($metadata, $context);
        }

        $this->tenantAuditLogger->log(
            companyId: $companyId,
            action: 'tenant.execution.alert.summary_emitted',
            actor: null,
            description: 'Execution alert threshold breached during ops summary run.',
            metadata: $metadata,
        );
    }
}





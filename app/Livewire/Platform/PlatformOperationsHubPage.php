<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Domains\Company\Models\TenantPilotWaveOutcome;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\Operations\ProductionReadinessValidator;
use App\Services\Operations\RuntimeOperationsHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Operations Hub')]
class PlatformOperationsHubPage extends Component
{
    use InteractsWithTenantCompanies;

    public bool $readyToLoad = false;

    public string $tab = 'execution';

    /**
     * @var array<int, string>
     */
    private const TABS = ['execution', 'checklist', 'incidents', 'rollout'];

    public function mount(): void
    {
        $this->authorizePlatformOperator();

        $requestedTab = strtolower(trim((string) request()->query('tab', '')));
        if (in_array($requestedTab, self::TABS, true)) {
            $this->tab = $requestedTab;
        }
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
    }

    public function updatedTab(): void
    {
        $this->tab = strtolower(trim($this->tab));

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'execution';
        }
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        $tenantCount = (int) $this->tenantCompaniesBaseQuery()->count();
        $tenantIds = $this->tenantCompanyIds();

        $executionSummary = $this->readyToLoad
            ? $this->executionSummary($tenantIds)
            : [
                'billing_failed' => 0,
                'payout_failed' => 0,
                'webhook_failed' => 0,
                'stuck_queued' => 0,
                'threshold_minutes' => 0,
                'last_recovery' => null,
            ];

        $checklistSummary = $this->readyToLoad
            ? $this->checklistSummary()
            : [
                'latest_tenant' => null,
                'active_tenants' => 0,
                'execution_enabled_tenants' => 0,
            ];

        $runtimeHealth = $this->readyToLoad
            ? app(RuntimeOperationsHealthService::class)->summary()
            : [
                'available' => true,
                'scheduler_heartbeat_at' => null,
                'scheduler_delay_minutes' => null,
                'failed_jobs_total' => 0,
                'failed_jobs_last_24h' => 0,
                'queued_jobs_total' => 0,
                'stale_jobs_total' => 0,
                'note' => null,
            ];

        $validationSummary = $this->readyToLoad
            ? app(ProductionReadinessValidator::class)->summary()
            : [
                'ok' => true,
                'blocking' => 0,
                'warning' => 0,
                'issues' => [],
            ];

        $incidentSummary = $this->readyToLoad
            ? $this->incidentSummary($tenantIds)
            : [
                'total_7d' => 0,
                'manual_7d' => 0,
                'auto_7d' => 0,
                'recent' => [],
            ];

        $rolloutSummary = $this->readyToLoad
            ? $this->rolloutSummary($tenantIds)
            : [
                'available' => true,
                'captures' => 0,
                'tenants_covered' => 0,
                'go' => 0,
                'hold' => 0,
                'no_go' => 0,
                'recent_outcomes' => [],
                'error' => null,
            ];

        return view('livewire.platform.platform-operations-hub-page', [
            'tenantCount' => $tenantCount,
            'executionSummary' => $executionSummary,
            'checklistSummary' => $checklistSummary,
            'runtimeHealth' => $runtimeHealth,
            'validationSummary' => $validationSummary,
            'incidentSummary' => $incidentSummary,
            'rolloutSummary' => $rolloutSummary,
            'tabs' => self::TABS,
        ]);
    }

    /**
     * @param  array<int, int>  $tenantIds
     * @return array{billing_failed:int,payout_failed:int,webhook_failed:int,stuck_queued:int,threshold_minutes:int,last_recovery:?string}
     */
    private function executionSummary(array $tenantIds): array
    {
        $thresholdMinutes = max(1, (int) config('execution.ops_recovery.older_than_minutes', 30));

        if ($tenantIds === []) {
            return [
                'billing_failed' => 0,
                'payout_failed' => 0,
                'webhook_failed' => 0,
                'stuck_queued' => 0,
                'threshold_minutes' => $thresholdMinutes,
                'last_recovery' => null,
            ];
        }

        $cutoff = Carbon::now()->subMinutes($thresholdMinutes);

        $billingFailed = (int) TenantSubscriptionBillingAttempt::query()
            ->whereIn('company_id', $tenantIds)
            ->where('attempt_status', 'failed')
            ->count();

        $payoutFailed = (int) RequestPayoutExecutionAttempt::query()
            ->whereIn('company_id', $tenantIds)
            ->where('execution_status', 'failed')
            ->count();

        $webhookFailed = (int) ExecutionWebhookEvent::query()
            ->whereIn('company_id', $tenantIds)
            ->where(function ($query): void {
                $query->where('processing_status', 'failed')
                    ->orWhere('verification_status', 'invalid');
            })
            ->count();

        $stuckBilling = (int) TenantSubscriptionBillingAttempt::query()
            ->whereIn('company_id', $tenantIds)
            ->where('attempt_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->count();

        $stuckPayout = (int) RequestPayoutExecutionAttempt::query()
            ->whereIn('company_id', $tenantIds)
            ->where('execution_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->count();

        $stuckWebhook = (int) ExecutionWebhookEvent::query()
            ->whereIn('company_id', $tenantIds)
            ->where('processing_status', 'queued')
            ->where('received_at', '<=', $cutoff)
            ->count();

        $latestRecovery = TenantAuditEvent::query()
            ->with('company:id,name')
            ->whereIn('company_id', $tenantIds)
            ->whereIn('action', [
                'tenant.execution.billing.process_stuck_queued',
                'tenant.execution.payout.process_stuck_queued',
                'tenant.execution.auto_recovery.run_summary',
                'tenant.execution.webhook.manual_reconciled_billing',
                'tenant.execution.webhook.manual_reconciled_payout',
            ])
            ->latest('event_at')
            ->latest('id')
            ->first();

        $lastRecovery = null;
        if ($latestRecovery) {
            $lastRecovery = sprintf(
                '%s | %s | %s',
                $this->executionActionLabel((string) $latestRecovery->action),
                (string) ($latestRecovery->company?->name ?? 'Tenant'),
                (string) ($latestRecovery->event_at?->format('M d, Y H:i') ?? '-')
            );
        }

        return [
            'billing_failed' => $billingFailed,
            'payout_failed' => $payoutFailed,
            'webhook_failed' => $webhookFailed,
            'stuck_queued' => (int) ($stuckBilling + $stuckPayout + $stuckWebhook),
            'threshold_minutes' => $thresholdMinutes,
            'last_recovery' => $lastRecovery,
        ];
    }

    /**
     * @return array{latest_tenant:?string,active_tenants:int,execution_enabled_tenants:int}
     */
    private function checklistSummary(): array
    {
        $latestTenant = $this->tenantCompaniesBaseQuery()
            ->latest('created_at')
            ->latest('id')
            ->value('name');

        $activeTenants = (int) $this->tenantCompaniesBaseQuery()
            ->where('lifecycle_status', 'active')
            ->count();

        $executionEnabledTenants = (int) $this->tenantCompaniesBaseQuery()
            ->whereHas('subscription', function ($query): void {
                $query->where('payment_execution_mode', 'execution_enabled');
            })
            ->count();

        return [
            'latest_tenant' => $latestTenant ? (string) $latestTenant : null,
            'active_tenants' => $activeTenants,
            'execution_enabled_tenants' => $executionEnabledTenants,
        ];
    }

    /**
     * @param  array<int, int>  $tenantIds
     * @return array{total_7d:int,manual_7d:int,auto_7d:int,recent:array<int,array{time:string,tenant:string,action:string,pipeline:string,actor:string}>}
     */
    private function incidentSummary(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [
                'total_7d' => 0,
                'manual_7d' => 0,
                'auto_7d' => 0,
                'recent' => [],
            ];
        }

        $since = Carbon::now()->subDays(7);

        $manualActions = [
            'tenant.execution.billing.retry_requested',
            'tenant.execution.payout.retry_requested',
            'tenant.execution.billing.process_stuck_queued',
            'tenant.execution.payout.process_stuck_queued',
            'tenant.execution.webhook.manual_reconciled_billing',
            'tenant.execution.webhook.manual_reconciled_payout',
            'tenant.execution.webhook.manual_failed',
            'tenant.execution.webhook.manual_ignored',
            'tenant.rollout.pilot_wave_outcome.recorded',
        ];

        $autoActions = [
            'tenant.execution.billing.auto_recovered_queued',
            'tenant.execution.payout.auto_recovered_queued',
            'tenant.execution.webhook.auto_recovered_queued',
            'tenant.execution.auto_recovery.run_summary',
            'tenant.execution.alert.summary_emitted',
            'tenant.execution.alert.notification.sent',
            'tenant.execution.alert.notification.failed',
            'tenant.execution.alert.notification.skipped',
            'tenant.execution.billing.handoff_to_treasury',
            'tenant.execution.payout.handoff_to_treasury',
            'tenant.execution.webhook.handoff_to_treasury',
        ];

        $allActions = array_values(array_unique(array_merge($manualActions, $autoActions)));

        $baseQuery = TenantAuditEvent::query()
            ->whereIn('company_id', $tenantIds)
            ->where('event_at', '>=', $since)
            ->whereIn('action', $allActions);

        $total = (int) (clone $baseQuery)->count();
        $manual = (int) (clone $baseQuery)->whereIn('action', $manualActions)->count();
        $auto = (int) (clone $baseQuery)->whereIn('action', $autoActions)->count();

        $recent = TenantAuditEvent::query()
            ->with(['company:id,name', 'actor:id,name'])
            ->whereIn('company_id', $tenantIds)
            ->whereIn('action', $allActions)
            ->latest('event_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (TenantAuditEvent $event): array {
                $metadata = (array) ($event->metadata ?? []);

                return [
                    'time' => (string) ($event->event_at?->format('M d, H:i') ?? '-'),
                    'tenant' => (string) ($event->company?->name ?? '-'),
                    'action' => $this->incidentActionLabel((string) $event->action),
                    'pipeline' => $this->incidentPipelineLabel((string) $event->action, $metadata),
                    'actor' => (string) ($event->actor?->name ?? 'System'),
                ];
            })
            ->all();

        return [
            'total_7d' => $total,
            'manual_7d' => $manual,
            'auto_7d' => $auto,
            'recent' => $recent,
        ];
    }

    /**
     * @param  array<int, int>  $tenantIds
     * @return array{available:bool,captures:int,tenants_covered:int,go:int,hold:int,no_go:int,recent_outcomes:array<int,array{time:string,tenant:string,wave:string,outcome:string,decided_by:string}>,error:?string}
     */
    private function rolloutSummary(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [
                'available' => true,
                'captures' => 0,
                'tenants_covered' => 0,
                'go' => 0,
                'hold' => 0,
                'no_go' => 0,
                'recent_outcomes' => [],
                'error' => null,
            ];
        }

        try {
            $capturesQuery = TenantPilotKpiCapture::query()->whereIn('company_id', $tenantIds);
            $captures = (int) (clone $capturesQuery)->count();
            $tenantsCovered = (int) (clone $capturesQuery)->distinct('company_id')->count('company_id');

            $outcomesQuery = TenantPilotWaveOutcome::query()->whereIn('company_id', $tenantIds);
            $go = (int) (clone $outcomesQuery)->where('outcome', TenantPilotWaveOutcome::OUTCOME_GO)->count();
            $hold = (int) (clone $outcomesQuery)->where('outcome', TenantPilotWaveOutcome::OUTCOME_HOLD)->count();
            $noGo = (int) (clone $outcomesQuery)->where('outcome', TenantPilotWaveOutcome::OUTCOME_NO_GO)->count();

            $recentOutcomes = TenantPilotWaveOutcome::query()
                ->with(['company:id,name', 'decidedBy:id,name'])
                ->whereIn('company_id', $tenantIds)
                ->latest('decision_at')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(function (TenantPilotWaveOutcome $outcome): array {
                    return [
                        'time' => (string) ($outcome->decision_at?->format('M d, H:i') ?? '-'),
                        'tenant' => (string) ($outcome->company?->name ?? '-'),
                        'wave' => (string) ($outcome->wave_label ?? '-'),
                        'outcome' => $this->outcomeLabel((string) $outcome->outcome),
                        'decided_by' => (string) ($outcome->decidedBy?->name ?? 'System'),
                    ];
                })
                ->all();

            return [
                'available' => true,
                'captures' => $captures,
                'tenants_covered' => $tenantsCovered,
                'go' => $go,
                'hold' => $hold,
                'no_go' => $noGo,
                'recent_outcomes' => $recentOutcomes,
                'error' => null,
            ];
        } catch (QueryException $exception) {
            // Rollout tables may be unavailable in partially migrated environments.
            return [
                'available' => false,
                'captures' => 0,
                'tenants_covered' => 0,
                'go' => 0,
                'hold' => 0,
                'no_go' => 0,
                'recent_outcomes' => [],
                'error' => 'Pilot rollout tables are not available in this environment yet.',
            ];
        }
    }

    private function executionActionLabel(string $action): string
    {
        return match ($action) {
            'tenant.execution.billing.process_stuck_queued' => 'Billing recovery',
            'tenant.execution.payout.process_stuck_queued' => 'Payout recovery',
            'tenant.execution.auto_recovery.run_summary' => 'Auto recovery summary',
            'tenant.execution.webhook.manual_reconciled_billing' => 'Webhook reconcile (billing)',
            'tenant.execution.webhook.manual_reconciled_payout' => 'Webhook reconcile (payout)',
            default => 'Execution action',
        };
    }

    private function incidentActionLabel(string $action): string
    {
        return match ($action) {
            'tenant.execution.billing.retry_requested' => 'Billing retry requested',
            'tenant.execution.payout.retry_requested' => 'Payout retry requested',
            'tenant.execution.billing.process_stuck_queued' => 'Billing manual recovery',
            'tenant.execution.payout.process_stuck_queued' => 'Payout manual recovery',
            'tenant.execution.webhook.manual_reconciled_billing' => 'Webhook reconcile (billing)',
            'tenant.execution.webhook.manual_reconciled_payout' => 'Webhook reconcile (payout)',
            'tenant.execution.webhook.manual_failed' => 'Webhook reconcile failed',
            'tenant.execution.webhook.manual_ignored' => 'Webhook reconcile ignored',
            'tenant.execution.billing.auto_recovered_queued' => 'Billing auto recovered',
            'tenant.execution.payout.auto_recovered_queued' => 'Payout auto recovered',
            'tenant.execution.webhook.auto_recovered_queued' => 'Webhook auto recovered',
            'tenant.execution.auto_recovery.run_summary' => 'Auto recovery summary',
            'tenant.execution.alert.summary_emitted' => 'Alert summary emitted',
            'tenant.execution.alert.notification.sent' => 'Alert delivery sent',
            'tenant.execution.alert.notification.failed' => 'Alert delivery failed',
            'tenant.execution.alert.notification.skipped' => 'Alert delivery skipped',
            'tenant.execution.billing.handoff_to_treasury' => 'Billing handoff to treasury',
            'tenant.execution.payout.handoff_to_treasury' => 'Payout handoff to treasury',
            'tenant.execution.webhook.handoff_to_treasury' => 'Webhook handoff to treasury',
            'tenant.rollout.pilot_wave_outcome.recorded' => 'Pilot wave outcome recorded',
            default => $action,
        };
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function incidentPipelineLabel(string $action, array $metadata = []): string
    {
        if (in_array($action, [
            'tenant.execution.auto_recovery.run_summary',
            'tenant.execution.alert.summary_emitted',
            'tenant.execution.alert.notification.sent',
            'tenant.execution.alert.notification.failed',
            'tenant.execution.alert.notification.skipped',
        ], true)) {
            $pipeline = strtolower(trim((string) ($metadata['pipeline'] ?? '')));

            return match ($pipeline) {
                'billing' => 'Billing',
                'payout' => 'Payout',
                'webhook' => 'Webhook',
                'procurement' => 'Procurement',
                'treasury' => 'Treasury',
                default => 'System',
            };
        }

        if (str_contains($action, '.billing.')) {
            return 'Billing';
        }

        if (str_contains($action, '.payout.')) {
            return 'Payout';
        }

        if (str_contains($action, '.webhook.')) {
            return 'Webhook';
        }

        return 'System';
    }

    private function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            TenantPilotWaveOutcome::OUTCOME_GO => 'Go',
            TenantPilotWaveOutcome::OUTCOME_HOLD => 'Hold',
            TenantPilotWaveOutcome::OUTCOME_NO_GO => 'No-go',
            default => ucfirst(str_replace('_', ' ', $outcome)),
        };
    }

    /**
     * @return array<int, int>
     */
    private function tenantCompanyIds(): array
    {
        return $this->tenantCompaniesBaseQuery()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}

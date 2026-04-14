<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Issue History')]
class IncidentHistoryPage extends Component
{
    use InteractsWithTenantCompanies;
    use WithPagination;

    public bool $readyToLoad = false;

    public string $tenantFilter = 'all';

    public string $pipelineFilter = 'all';

    public string $incidentTypeFilter = 'all';

    public string $actorFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorizePlatformOperator();
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
    }

    public function updatedTenantFilter(): void
    {
        $this->resetPage('incidentsPage');
    }

    public function updatedPipelineFilter(): void
    {
        $this->resetPage('incidentsPage');
    }

    public function updatedIncidentTypeFilter(): void
    {
        $this->resetPage('incidentsPage');
    }

    public function updatedActorFilter(): void
    {
        $this->resetPage('incidentsPage');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage('incidentsPage');
    }

    public function updatedDateTo(): void
    {
        $this->resetPage('incidentsPage');
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [15, 30, 50], true)) {
            $this->perPage = 15;
        }

        $this->resetPage('incidentsPage');
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizePlatformOperator();

        $fileName = 'incident_history_'.now()->format('Ymd_His').'.csv';

        $query = $this->filteredIncidentsQuery()
            ->with(['company:id,name', 'actor:id,name'])
            ->latest('event_at')
            ->latest('id');

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            fputcsv($stream, [
                'Timestamp',
                'Tenant',
                'Pipeline',
                'Incident Type',
                'Action',
                'Actor',
                'Description',
                'Metadata',
            ]);

            $query->chunk(250, function ($events) use ($stream): void {
                foreach ($events as $event) {
                    $metadata = (array) ($event->metadata ?? []);

                    fputcsv($stream, [
                        (string) ($event->event_at?->format('Y-m-d H:i:s') ?? ''),
                        (string) ($event->company?->name ?? '-'),
                        $this->pipelineLabelFor((string) $event->action, $metadata),
                        $this->incidentTypeLabelForAction((string) $event->action),
                        $this->actionLabel((string) $event->action),
                        (string) ($event->actor?->name ?? 'System'),
                        $this->detailsForEvent($event),
                        json_encode($metadata, JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        $tenantOptions = $this->tenantCompaniesBaseQuery()
            ->orderBy('name')
            ->get(['id', 'name']);

        $incidents = $this->readyToLoad
            ? $this->filteredIncidentsQuery()
                ->with(['company:id,name', 'actor:id,name'])
                ->latest('event_at')
                ->latest('id')
                ->paginate($this->perPage, ['*'], 'incidentsPage')
            : $this->emptyPaginator($this->perPage, 'incidentsPage');

        $stats = $this->readyToLoad
            ? $this->summaryStats()
            : [
                'total' => 0,
                'manual' => 0,
                'auto' => 0,
                'manual_failed' => 0,
            ];

        $trend = $this->readyToLoad
            ? $this->trendSeries()
            : [
                'rows' => [],
                'max_total' => 1,
            ];

        return view('livewire.platform.incident-history-page', [
            'tenantOptions' => $tenantOptions,
            'incidents' => $incidents,
            'stats' => $stats,
            'trend' => $trend,
            'incidentTypeOptions' => $this->incidentTypeOptions(),
        ]);
    }

    public function actionLabel(string $action): string
    {
        return match ($action) {
            'tenant.execution.billing.retry_requested' => 'Billing retry requested',
            'tenant.execution.payout.retry_requested' => 'Payout retry requested',
            'tenant.execution.billing.process_stuck_queued' => 'Billing manual recovery',
            'tenant.execution.payout.process_stuck_queued' => 'Payout manual recovery',
            'tenant.execution.webhook.manual_reconciled_billing' => 'Webhook manual reconcile (billing)',
            'tenant.execution.webhook.manual_reconciled_payout' => 'Webhook manual reconcile (payout)',
            'tenant.execution.webhook.manual_failed' => 'Webhook manual reconcile failed',
            'tenant.execution.webhook.manual_ignored' => 'Webhook manual reconcile ignored',
            'tenant.execution.billing.auto_recovered_queued' => 'Billing auto recovered',
            'tenant.execution.payout.auto_recovered_queued' => 'Payout auto recovered',
            'tenant.execution.webhook.auto_recovered_queued' => 'Webhook auto recovered',
            'tenant.execution.auto_recovery.run_summary' => 'Auto recovery run summary',
            'tenant.execution.alert.summary_emitted' => 'Execution alert summary',
            'tenant.execution.alert.notification.sent' => 'Execution alert delivery sent',
            'tenant.execution.alert.notification.failed' => 'Execution alert delivery failed',
            'tenant.execution.alert.notification.skipped' => 'Execution alert delivery skipped',
            'tenant.execution.billing.handoff_to_treasury' => 'Billing handoff to treasury',
            'tenant.execution.payout.handoff_to_treasury' => 'Payout handoff to treasury',
            'tenant.execution.webhook.handoff_to_treasury' => 'Webhook handoff to treasury',
            'tenant.rollout.pilot_wave_outcome.recorded' => 'Pilot wave outcome recorded',
            default => $action,
        };
    }

    public function pipelineLabelFor(string $action, array $metadata = []): string
    {
        $pipeline = $this->pipelineForAction($action, $metadata);

        return match ($pipeline) {
            'billing' => 'Billing',
            'payout' => 'Payout',
            'webhook' => 'Webhook',
            'procurement' => 'Procurement',
            'treasury' => 'Treasury',
            default => 'System',
        };
    }

    public function incidentTypeLabelForAction(string $action): string
    {
        return match ($this->incidentTypeForAction($action)) {
            'retry' => 'Retry',
            'manual_recovery' => 'Manual Recovery',
            'manual_reconcile' => 'Manual Reconcile',
            'manual_failed' => 'Manual Reconcile Failed',
            'manual_ignored' => 'Manual Reconcile Ignored',
            'auto_recovery' => 'Auto Recovery',
            'auto_recovery_summary' => 'Auto Recovery Summary',
            'alert_summary' => 'Alert Summary',
            'alert_delivery' => 'Alert Delivery',
            'treasury_handoff' => 'Treasury Handoff',
            'rollout_decision' => 'Rollout Decision',
            default => 'Other',
        };
    }

    public function detailsForEvent(TenantAuditEvent $event): string
    {
        $metadata = (array) ($event->metadata ?? []);
        $action = (string) $event->action;

        if ($action === 'tenant.execution.auto_recovery.run_summary') {
            $matched = (int) ($metadata['matched'] ?? 0);
            $processed = (int) ($metadata['processed'] ?? 0);
            $skipped = (int) ($metadata['skipped'] ?? 0);
            $rejected = (int) ($metadata['rejected'] ?? 0);
            $threshold = (int) ($metadata['older_than_minutes'] ?? 0);

            return 'matched '.$matched.', processed '.$processed.', skipped '.$skipped.', rejected '.$rejected.', threshold '.$threshold.' mins';
        }

        if ($action === 'tenant.execution.alert.summary_emitted') {
            $count = (int) ($metadata['count'] ?? 0);
            $threshold = (int) ($metadata['threshold'] ?? 0);
            $ageHours = (int) ($metadata['age_hours'] ?? 0);

            if ($ageHours > 0) {
                return 'count '.$count.', threshold '.$threshold.', age '.$ageHours.' hrs';
            }

            $windowMinutes = (int) ($metadata['window_minutes'] ?? 0);

            return 'count '.$count.', threshold '.$threshold.', window '.$windowMinutes.' mins';
        }

        if (in_array($action, ['tenant.execution.alert.notification.sent', 'tenant.execution.alert.notification.failed', 'tenant.execution.alert.notification.skipped'], true)) {
            $channel = (string) ($metadata['channel'] ?? 'in_app');
            $recipientCount = (int) ($metadata['recipient_count'] ?? 0);
            $failedCount = (int) ($metadata['failed_count'] ?? 0);
            $missingEmailCount = (int) ($metadata['missing_email_count'] ?? 0);

            $parts = ['channel '.$channel, 'recipients '.$recipientCount];
            if ($failedCount > 0) {
                $parts[] = 'failed '.$failedCount;
            }
            if ($missingEmailCount > 0) {
                $parts[] = 'missing email '.$missingEmailCount;
            }

            return implode(', ', $parts);
        }


        if ($action === 'tenant.rollout.pilot_wave_outcome.recorded') {
            $waveLabel = (string) ($metadata['wave_label'] ?? '-');
            $outcome = ucfirst(str_replace('_', ' ', (string) ($metadata['outcome'] ?? 'unknown')));
            $decisionAt = (string) ($metadata['decision_at'] ?? '');

            return $decisionAt !== ''
                ? 'wave '.$waveLabel.', outcome '.$outcome.', decided '.$decisionAt
                : 'wave '.$waveLabel.', outcome '.$outcome;
        }

        return (string) ($event->description ?? '-');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function incidentTypeOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'All incident types'],
            ['value' => 'retry', 'label' => 'Retry'],
            ['value' => 'manual_recovery', 'label' => 'Manual recovery'],
            ['value' => 'manual_reconcile', 'label' => 'Manual reconcile'],
            ['value' => 'manual_failed', 'label' => 'Manual reconcile failed'],
            ['value' => 'manual_ignored', 'label' => 'Manual reconcile ignored'],
            ['value' => 'auto_recovery', 'label' => 'Auto recovery'],
            ['value' => 'auto_recovery_summary', 'label' => 'Auto recovery summary'],
            ['value' => 'alert_summary', 'label' => 'Alert summary'],
            ['value' => 'alert_delivery', 'label' => 'Alert delivery'],
            ['value' => 'treasury_handoff', 'label' => 'Treasury handoff'],
            ['value' => 'rollout_decision', 'label' => 'Rollout decision'],
        ];
    }

    private function filteredIncidentsQuery(): Builder
    {
        $query = $this->incidentsBaseQuery();

        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        $this->applyPipelineFilter($query);
        $this->applyIncidentTypeFilter($query);

        if ($this->actorFilter === 'system') {
            $query->whereNull('actor_user_id');
        } elseif ($this->actorFilter === 'user') {
            $query->whereNotNull('actor_user_id');
        }

        $dateFrom = $this->normalizeDate($this->dateFrom);
        if ($dateFrom) {
            $query->whereDate('event_at', '>=', $dateFrom);
        }

        $dateTo = $this->normalizeDate($this->dateTo);
        if ($dateTo) {
            $query->whereDate('event_at', '<=', $dateTo);
        }

        return $query;
    }

    private function applyPipelineFilter(Builder $query): void
    {
        if (! in_array($this->pipelineFilter, ['billing', 'payout', 'webhook', 'procurement', 'treasury', 'system'], true)) {
            return;
        }

        if ($this->pipelineFilter === 'system') {
            $query->whereIn('action', [
                'tenant.execution.auto_recovery.run_summary',
                'tenant.execution.alert.summary_emitted',
                'tenant.rollout.pilot_wave_outcome.recorded',
            ]);

            return;
        }

        $pipelineActions = match ($this->pipelineFilter) {
            'billing' => $this->billingPipelineActions(),
            'payout' => $this->payoutPipelineActions(),
            'webhook' => $this->webhookPipelineActions(),
            default => [],
        };

        $pipelineFilter = $this->pipelineFilter;

        $query->where(function (Builder $builder) use ($pipelineActions, $pipelineFilter): void {
            $builder->whereIn('action', $pipelineActions)
                ->orWhere(function (Builder $summaryQuery) use ($pipelineFilter): void {
                    $summaryQuery->whereIn('action', [
                        'tenant.execution.auto_recovery.run_summary',
                'tenant.execution.alert.summary_emitted',
                'tenant.rollout.pilot_wave_outcome.recorded',
                        'tenant.execution.alert.notification.sent',
                        'tenant.execution.alert.notification.failed',
                        'tenant.execution.alert.notification.skipped',
                    ])->where('metadata->pipeline', $pipelineFilter);
                });
        });
    }

    private function applyIncidentTypeFilter(Builder $query): void
    {
        $actions = match ($this->incidentTypeFilter) {
            'retry' => [
                'tenant.execution.billing.retry_requested',
                'tenant.execution.payout.retry_requested',
            ],
            'manual_recovery' => [
                'tenant.execution.billing.process_stuck_queued',
                'tenant.execution.payout.process_stuck_queued',
            ],
            'manual_reconcile' => [
                'tenant.execution.webhook.manual_reconciled_billing',
                'tenant.execution.webhook.manual_reconciled_payout',
            ],
            'manual_failed' => ['tenant.execution.webhook.manual_failed'],
            'manual_ignored' => ['tenant.execution.webhook.manual_ignored'],
            'auto_recovery' => [
                'tenant.execution.billing.auto_recovered_queued',
                'tenant.execution.payout.auto_recovered_queued',
                'tenant.execution.webhook.auto_recovered_queued',
            ],
            'auto_recovery_summary' => ['tenant.execution.auto_recovery.run_summary'],
            'alert_summary' => ['tenant.execution.alert.summary_emitted'],
            'alert_delivery' => [
                'tenant.execution.alert.notification.sent',
                'tenant.execution.alert.notification.failed',
                'tenant.execution.alert.notification.skipped',
            ],
            'treasury_handoff' => [
                'tenant.execution.billing.handoff_to_treasury',
                'tenant.execution.payout.handoff_to_treasury',
                'tenant.execution.webhook.handoff_to_treasury',
            ],
            'rollout_decision' => [
                'tenant.rollout.pilot_wave_outcome.recorded',
            ],
            default => [],
        };

        if ($actions !== []) {
            $query->whereIn('action', $actions);
        }
    }

    private function incidentsBaseQuery(): Builder
    {
        // Scope the incident timeline to execution-focused audit actions only.
        return TenantAuditEvent::query()
            ->whereIn('company_id', $this->tenantCompanyIds())
            ->whereIn('action', $this->incidentActions());
    }

    /**
     * @return array{total:int,manual:int,auto:int,manual_failed:int}
     */
    private function summaryStats(): array
    {
        $query = $this->filteredIncidentsQuery();

        return [
            'total' => (int) (clone $query)->count(),
            'manual' => (int) (clone $query)->whereIn('action', $this->manualActionScope())->count(),
            'auto' => (int) (clone $query)->whereIn('action', $this->autoActionScope())->count(),
            'manual_failed' => (int) (clone $query)->where('action', 'tenant.execution.webhook.manual_failed')->count(),
        ];
    }

    /**
     * @return array{rows:array<int,array{day:string,label:string,total:int,billing:int,payout:int,webhook:int,system:int}>,max_total:int}
     */
    private function trendSeries(): array
    {
        $start = Carbon::now()->subDays(6)->startOfDay();

        $rows = [];
        for ($index = 0; $index < 7; $index++) {
            $day = $start->copy()->addDays($index);
            $dayKey = $day->format('Y-m-d');

            $rows[$dayKey] = [
                'day' => $dayKey,
                'label' => $day->format('M d'),
                'total' => 0,
                'billing' => 0,
                'payout' => 0,
                'webhook' => 0,
                'system' => 0,
            ];
        }

        // Keep trend rendering cheap by limiting aggregation to the visible 7-day window.
        $events = $this->filteredIncidentsQuery()
            ->where('event_at', '>=', $start)
            ->get(['event_at', 'action', 'metadata']);

        foreach ($events as $event) {
            $dayKey = $event->event_at?->format('Y-m-d');
            if (! $dayKey || ! isset($rows[$dayKey])) {
                continue;
            }

            $metadata = (array) ($event->metadata ?? []);
            $pipeline = $this->pipelineForAction((string) $event->action, $metadata);

            $rows[$dayKey]['total']++;
            if (isset($rows[$dayKey][$pipeline])) {
                $rows[$dayKey][$pipeline]++;
            } else {
                $rows[$dayKey]['system']++;
            }
        }

        $maxTotal = 1;
        foreach ($rows as $row) {
            $maxTotal = max($maxTotal, (int) $row['total']);
        }

        return [
            'rows' => array_values($rows),
            'max_total' => $maxTotal,
        ];
    }

    private function incidentTypeForAction(string $action): string
    {
        return match ($action) {
            'tenant.execution.billing.retry_requested',
            'tenant.execution.payout.retry_requested' => 'retry',
            'tenant.execution.billing.process_stuck_queued',
            'tenant.execution.payout.process_stuck_queued' => 'manual_recovery',
            'tenant.execution.webhook.manual_reconciled_billing',
            'tenant.execution.webhook.manual_reconciled_payout' => 'manual_reconcile',
            'tenant.execution.webhook.manual_failed' => 'manual_failed',
            'tenant.execution.webhook.manual_ignored' => 'manual_ignored',
            'tenant.execution.billing.auto_recovered_queued',
            'tenant.execution.payout.auto_recovered_queued',
            'tenant.execution.webhook.auto_recovered_queued' => 'auto_recovery',
            'tenant.execution.auto_recovery.run_summary' => 'auto_recovery_summary',
            'tenant.execution.alert.summary_emitted' => 'alert_summary',
            'tenant.execution.alert.notification.sent',
            'tenant.execution.alert.notification.failed',
            'tenant.execution.alert.notification.skipped' => 'alert_delivery',
            'tenant.execution.billing.handoff_to_treasury',
            'tenant.execution.payout.handoff_to_treasury',
            'tenant.execution.webhook.handoff_to_treasury' => 'treasury_handoff',
            'tenant.rollout.pilot_wave_outcome.recorded' => 'rollout_decision',
            default => 'other',
        };
    }

    private function pipelineForAction(string $action, array $metadata): string
    {
        if (in_array($action, ['tenant.execution.auto_recovery.run_summary', 'tenant.execution.alert.summary_emitted', 'tenant.execution.alert.notification.sent', 'tenant.execution.alert.notification.failed', 'tenant.execution.alert.notification.skipped'], true)) {
            $pipeline = strtolower(trim((string) ($metadata['pipeline'] ?? '')));

            return in_array($pipeline, ['billing', 'payout', 'webhook', 'procurement', 'treasury'], true)
                ? $pipeline
                : 'system';
        }

        if (str_contains($action, '.billing.')) {
            return 'billing';
        }

        if (str_contains($action, '.payout.')) {
            return 'payout';
        }

        if (str_contains($action, '.webhook.')) {
            return 'webhook';
        }

        return 'system';
    }

    /**
     * @return array<int, string>
     */
    private function incidentActions(): array
    {
        return [
            'tenant.execution.billing.retry_requested',
            'tenant.execution.payout.retry_requested',
            'tenant.execution.billing.process_stuck_queued',
            'tenant.execution.payout.process_stuck_queued',
            'tenant.execution.webhook.manual_reconciled_billing',
            'tenant.execution.webhook.manual_reconciled_payout',
            'tenant.execution.webhook.manual_failed',
            'tenant.execution.webhook.manual_ignored',
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
            'tenant.rollout.pilot_wave_outcome.recorded',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function manualActionScope(): array
    {
        return [
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
    }

    /**
     * @return array<int, string>
     */
    private function autoActionScope(): array
    {
        return [
            'tenant.execution.billing.auto_recovered_queued',
            'tenant.execution.payout.auto_recovered_queued',
            'tenant.execution.webhook.auto_recovered_queued',
            'tenant.execution.auto_recovery.run_summary',
            'tenant.execution.alert.notification.sent',
            'tenant.execution.alert.notification.failed',
            'tenant.execution.alert.notification.skipped',
            'tenant.execution.billing.handoff_to_treasury',
            'tenant.execution.payout.handoff_to_treasury',
            'tenant.execution.webhook.handoff_to_treasury',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function billingPipelineActions(): array
    {
        return [
            'tenant.execution.billing.retry_requested',
            'tenant.execution.billing.process_stuck_queued',
            'tenant.execution.billing.auto_recovered_queued',
            'tenant.execution.billing.handoff_to_treasury',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function payoutPipelineActions(): array
    {
        return [
            'tenant.execution.payout.retry_requested',
            'tenant.execution.payout.process_stuck_queued',
            'tenant.execution.payout.auto_recovered_queued',
            'tenant.execution.payout.handoff_to_treasury',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function webhookPipelineActions(): array
    {
        return [
            'tenant.execution.webhook.manual_reconciled_billing',
            'tenant.execution.webhook.manual_reconciled_payout',
            'tenant.execution.webhook.manual_failed',
            'tenant.execution.webhook.manual_ignored',
            'tenant.execution.webhook.auto_recovered_queued',
            'tenant.execution.webhook.handoff_to_treasury',
        ];
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

    private function normalizeDate(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => $pageName,
        ]);
    }
}















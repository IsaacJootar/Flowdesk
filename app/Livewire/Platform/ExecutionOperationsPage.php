<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\Execution\ExecutionWebhookManualReconciliationService;
use App\Services\Execution\RequestPayoutExecutionAttemptProcessor;
use App\Services\Execution\SubscriptionBillingAttemptProcessor;
use App\Services\TenantAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payment Provider Operations')]
class ExecutionOperationsPage extends Component
{
    use InteractsWithTenantCompanies;
    use WithPagination;

    public bool $readyToLoad = false;

    public string $tenantFilter = 'all';

    public string $providerFilter = 'all';

    public string $pipelineFilter = 'all';

    public string $statusFilter = 'all';

    public bool $onlyOlderThan = false;

    public string $tableOlderThanMinutes = '30';

    public string $batchOlderThanMinutes = '30';

    public int $billingPerPage = 10;

    public int $payoutPerPage = 10;

    public int $webhookPerPage = 10;

    public int $recoveryRunsPerPage = 10;

    public int $alertSummariesPerPage = 10;

    public string $billingRetryReason = '';

    public string $payoutRetryReason = '';

    public string $webhookReconcileReason = '';

    public string $batchReason = '';

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

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
        $this->resetPagination();
    }

    public function updatedProviderFilter(): void
    {
        $this->resetPagination();
    }

    public function updatedPipelineFilter(): void
    {
        $this->resetPagination();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPagination();
    }

    public function updatedOnlyOlderThan(): void
    {
        $this->resetPagination();
    }

    public function updatedTableOlderThanMinutes(): void
    {
        $this->tableOlderThanMinutes = (string) $this->normalizeTableOlderThanMinutes();
        $this->resetPagination();
    }

    public function updatedBatchOlderThanMinutes(): void
    {
        $this->batchOlderThanMinutes = (string) $this->normalizeBatchOlderThanMinutes();
    }

    public function retryBillingAttempt(int $attemptId): void
    {
        $this->authorizePlatformOperator();
        $reason = trim($this->billingRetryReason);

        $this->validate([
            'billingRetryReason' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $attempt = $this->billingAttemptsBaseQuery()->find($attemptId);
        if (! $attempt) {
            $this->setFeedbackError('Billing attempt was not found in current tenant scope.');

            return;
        }

        if (in_array((string) $attempt->attempt_status, ['settled', 'reversed'], true)) {
            $this->setFeedbackError('Settled or reversed attempts cannot be retried.');

            return;
        }

        $actor = Auth::user();

        $attempt->forceFill([
            'attempt_status' => 'queued',
            'queued_at' => now(),
            'next_retry_at' => null,
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
            'updated_by' => $actor?->id,
            'metadata' => $this->appendManualActionMetadata(
                (array) ($attempt->metadata ?? []),
                'manual_retry',
                $reason,
                (int) ($actor?->id ?? 0),
            ),
        ])->save();

        app(TenantAuditLogger::class)->log(
            companyId: (int) $attempt->company_id,
            action: 'tenant.execution.billing.retry_requested',
            actor: $actor,
            description: 'Billing execution retry requested from operations center.',
            entityType: TenantSubscriptionBillingAttempt::class,
            entityId: (int) $attempt->id,
            metadata: [
                'reason' => $reason,
                'provider' => (string) $attempt->provider_key,
            ],
        );

        $processed = app(SubscriptionBillingAttemptProcessor::class)->processAttemptById((int) $attempt->id);
        $this->billingRetryReason = '';

        if (! $processed) {
            $this->setFeedbackError('Billing retry was queued but could not be processed immediately.');

            return;
        }

        $attempt->refresh();
        if ((string) $attempt->attempt_status === 'skipped') {
            $this->setFeedback('Billing retry processed, but status is skipped because this tenant uses a no-op provider.');

            return;
        }

        $this->setFeedback('Billing attempt retried.');
    }

    public function retryPayoutAttempt(int $attemptId): void
    {
        $this->authorizePlatformOperator();
        $reason = trim($this->payoutRetryReason);

        $this->validate([
            'payoutRetryReason' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $attempt = $this->payoutAttemptsBaseQuery()->find($attemptId);
        if (! $attempt) {
            $this->setFeedbackError('Payout attempt was not found in current tenant scope.');

            return;
        }

        if (in_array((string) $attempt->execution_status, ['settled', 'reversed'], true)) {
            $this->setFeedbackError('Settled or reversed payout attempts cannot be retried.');

            return;
        }

        $actor = Auth::user();

        $attempt->forceFill([
            'execution_status' => 'queued',
            'queued_at' => now(),
            'next_retry_at' => null,
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
            'updated_by' => $actor?->id,
            'metadata' => $this->appendManualActionMetadata(
                (array) ($attempt->metadata ?? []),
                'manual_retry',
                $reason,
                (int) ($actor?->id ?? 0),
            ),
        ])->save();

        app(TenantAuditLogger::class)->log(
            companyId: (int) $attempt->company_id,
            action: 'tenant.execution.payout.retry_requested',
            actor: $actor,
            description: 'Payout execution retry requested from operations center.',
            entityType: RequestPayoutExecutionAttempt::class,
            entityId: (int) $attempt->id,
            metadata: [
                'reason' => $reason,
                'provider' => (string) $attempt->provider_key,
            ],
        );

        $processed = app(RequestPayoutExecutionAttemptProcessor::class)->processAttemptById((int) $attempt->id);
        $this->payoutRetryReason = '';

        if (! $processed) {
            $this->setFeedbackError('Payout retry was queued but could not be processed immediately.');

            return;
        }

        $attempt->refresh();
        if ((string) $attempt->execution_status === 'skipped') {
            $this->setFeedback('Payout retry processed, but status is skipped because this tenant uses a no-op provider.');

            return;
        }

        $this->setFeedback('Payout attempt retried.');
    }

    public function reconcileWebhookEvent(int $eventId): void
    {
        $this->authorizePlatformOperator();

        $this->validate([
            'webhookReconcileReason' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $event = $this->webhookEventsBaseQuery()->find($eventId);
        if (! $event) {
            $this->setFeedbackError('Webhook event was not found in current tenant scope.');

            return;
        }

        $result = app(ExecutionWebhookManualReconciliationService::class)
            ->reconcile($event, $this->webhookReconcileReason, Auth::user());

        $this->webhookReconcileReason = '';

        if (! $result['ok']) {
            $this->setFeedbackError((string) $result['message']);

            return;
        }

        $this->setFeedback((string) $result['message']);
    }

    public function processStuckBillingQueued(): void
    {
        $this->authorizePlatformOperator();
        $reason = trim($this->batchReason);

        $this->validate([
            'batchReason' => ['required', 'string', 'min:4', 'max:500'],
            'batchOlderThanMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
        ]);

        $threshold = $this->normalizeBatchOlderThanMinutes();
        $cutoff = Carbon::now()->subMinutes($threshold);
        $processor = app(SubscriptionBillingAttemptProcessor::class);
        $actor = Auth::user();

        $processed = 0;
        $skipped = 0;
        $missingSubscription = 0;
        $stateChanged = 0;
        $otherRejects = 0;

        $attempts = $this->billingAttemptsBaseQuery()
            ->where('attempt_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $matched = $attempts->count();

        $attempts->each(function (TenantSubscriptionBillingAttempt $attempt) use ($processor, $reason, $actor, &$processed, &$skipped, &$missingSubscription, &$stateChanged, &$otherRejects): void {
            if ($processor->processAttemptById((int) $attempt->id)) {
                $processed++;

                app(TenantAuditLogger::class)->log(
                    companyId: (int) $attempt->company_id,
                    action: 'tenant.execution.billing.process_stuck_queued',
                    actor: $actor,
                    description: 'Stuck queued billing attempt processed manually from operations center.',
                    entityType: TenantSubscriptionBillingAttempt::class,
                    entityId: (int) $attempt->id,
                    metadata: ['reason' => $reason],
                );

                $latest = TenantSubscriptionBillingAttempt::query()->select('attempt_status')->find((int) $attempt->id);
                if ((string) ($latest?->attempt_status ?? '') === 'skipped') {
                    $skipped++;
                }

                return;
            }

            $latest = TenantSubscriptionBillingAttempt::query()
                ->with(['subscription.company:id'])
                ->find((int) $attempt->id);

            if (! $latest) {
                $stateChanged++;

                return;
            }

            if (in_array((string) $latest->attempt_status, ['settled', 'reversed'], true)) {
                $stateChanged++;

                return;
            }

            if (! $latest->subscription || ! $latest->subscription->company) {
                $missingSubscription++;

                return;
            }

            $otherRejects++;
        });

        if ($matched === 0) {
            $this->setFeedback('No queued billing attempts matched the recovery age threshold ('.$threshold.' mins).');

            return;
        }

        if ($processed === 0) {
            $this->setFeedback(
                'Found '.$matched.' queued billing attempts older than '.$threshold.' mins, but none were processed. '
                .'Check provider/config/state and retry. '.'Breakdown: missing subscription '.$missingSubscription.', state changed '.$stateChanged.', other '.$otherRejects.'.'
            );

            return;
        }

        $message = 'Processed '.$processed.' of '.$matched.' queued billing attempts older than '.$threshold.' mins.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' ended as skipped (no-op provider).';
        }

        $this->setFeedback($message);
    }
    public function processStuckPayoutQueued(): void
    {
        $this->authorizePlatformOperator();
        $reason = trim($this->batchReason);

        $this->validate([
            'batchReason' => ['required', 'string', 'min:4', 'max:500'],
            'batchOlderThanMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
        ]);

        $threshold = $this->normalizeBatchOlderThanMinutes();
        $cutoff = Carbon::now()->subMinutes($threshold);
        $processor = app(RequestPayoutExecutionAttemptProcessor::class);
        $actor = Auth::user();

        $processed = 0;
        $skipped = 0;
        $missingRequest = 0;
        $missingSubscription = 0;
        $stateChanged = 0;
        $otherRejects = 0;

        $attempts = $this->payoutAttemptsBaseQuery()
            ->where('execution_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $matched = $attempts->count();

        $attempts->each(function (RequestPayoutExecutionAttempt $attempt) use ($processor, $reason, $actor, &$processed, &$skipped, &$missingRequest, &$missingSubscription, &$stateChanged, &$otherRejects): void {
            if ($processor->processAttemptById((int) $attempt->id)) {
                $processed++;

                app(TenantAuditLogger::class)->log(
                    companyId: (int) $attempt->company_id,
                    action: 'tenant.execution.payout.process_stuck_queued',
                    actor: $actor,
                    description: 'Stuck queued payout attempt processed manually from operations center.',
                    entityType: RequestPayoutExecutionAttempt::class,
                    entityId: (int) $attempt->id,
                    metadata: ['reason' => $reason],
                );

                $latest = RequestPayoutExecutionAttempt::query()->select('execution_status')->find((int) $attempt->id);
                if ((string) ($latest?->execution_status ?? '') === 'skipped') {
                    $skipped++;
                }

                return;
            }

            $latest = RequestPayoutExecutionAttempt::query()
                ->with(['subscription:id'])
                ->find((int) $attempt->id);

            if (! $latest) {
                $stateChanged++;

                return;
            }

            if (in_array((string) $latest->execution_status, ['settled', 'reversed'], true)) {
                $stateChanged++;

                return;
            }

            $requestExists = SpendRequest::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->whereKey((int) $latest->request_id)
                ->exists();

            if (! $requestExists) {
                $missingRequest++;

                return;
            }

            if (! $latest->subscription) {
                $missingSubscription++;

                return;
            }

            $otherRejects++;
        });

        if ($matched === 0) {
            $this->setFeedback('No queued payout attempts matched the recovery age threshold ('.$threshold.' mins).');

            return;
        }

        if ($processed === 0) {
            $this->setFeedback(
                'Found '.$matched.' queued payout attempts older than '.$threshold.' mins, but none were processed. '
                .'Check provider/config/state and retry. '.'Breakdown: missing request '.$missingRequest.', missing subscription '.$missingSubscription.', state changed '.$stateChanged.', other '.$otherRejects.'.'
            );

            return;
        }

        $message = 'Processed '.$processed.' of '.$matched.' queued payout attempts older than '.$threshold.' mins.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' ended as skipped (no-op provider).';
        }

        $this->setFeedback($message);
    }
    public function processStuckWebhookQueue(): void
    {
        $this->authorizePlatformOperator();

        $this->validate([
            'batchReason' => ['required', 'string', 'min:4', 'max:500'],
            'batchOlderThanMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
        ]);

        $threshold = $this->normalizeBatchOlderThanMinutes();
        $cutoff = Carbon::now()->subMinutes($threshold);
        $service = app(ExecutionWebhookManualReconciliationService::class);
        $actor = Auth::user();
        $reason = trim($this->batchReason);

        $processed = 0;
        $invalidVerification = 0;
        $missingLinkedAttempt = 0;
        $stateChanged = 0;
        $otherRejects = 0;

        $events = $this->webhookEventsBaseQuery()
            ->where('processing_status', 'queued')
            ->where('received_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $matched = $events->count();

        $events->each(function (ExecutionWebhookEvent $event) use ($service, $actor, $reason, &$processed, &$invalidVerification, &$missingLinkedAttempt, &$stateChanged, &$otherRejects): void {
            $result = $service->reconcile($event, $reason, $actor);
            if ($result['ok']) {
                $processed++;

                return;
            }

            $message = strtolower(trim((string) ($result['message'] ?? '')));

            if (str_contains($message, 'only valid webhook events')) {
                $invalidVerification++;

                return;
            }

            if (str_contains($message, 'no linked billing/payout attempt')) {
                $missingLinkedAttempt++;

                return;
            }

            $latest = ExecutionWebhookEvent::query()->find((int) $event->id);
            if (! $latest || (string) $latest->processing_status !== 'queued') {
                $stateChanged++;

                return;
            }

            $otherRejects++;
        });

        if ($matched === 0) {
            $this->setFeedback('No queued webhook events matched the recovery age threshold ('.$threshold.' mins).');

            return;
        }

        if ($processed === 0) {
            $this->setFeedback(
                'Found '.$matched.' queued webhook events older than '.$threshold.' mins, but none were processed. '
                .'Check provider/config/state and retry. '.'Breakdown: invalid verification '.$invalidVerification.', missing linked attempt '.$missingLinkedAttempt.', state changed '.$stateChanged.', other '.$otherRejects.'.'
            );

            return;
        }

        $this->setFeedback('Processed '.$processed.' of '.$matched.' queued webhook events older than '.$threshold.' mins.');
    }
    public function render(): View
    {
        $this->authorizePlatformOperator();

        $tenantOptions = $this->tenantCompaniesBaseQuery()
            ->orderBy('name')
            ->get(['id', 'name']);

        $providerOptions = $this->readyToLoad ? $this->providerOptions() : [];

        $stats = $this->readyToLoad ? $this->stats() : [
            'billing_failed' => 0,
            'payout_failed' => 0,
            'webhook_failed' => 0,
            'stuck_queued' => 0,
            'incident_window_minutes' => 60,
            'failure_rate_percent' => 0.0,
            'skipped_rate_percent' => 0.0,
            'oldest_queue_age_minutes' => null,
            'last_recovery_outcome' => null,
        ];

        $billingAttempts = $this->readyToLoad && $this->showPipeline('billing')
            ? $this->filteredBillingAttemptsQuery()->latest('id')->paginate($this->billingPerPage, ['*'], 'billingPage')
            : $this->emptyPaginator($this->billingPerPage, 'billingPage');

        $payoutAttempts = $this->readyToLoad && $this->showPipeline('payout')
            ? $this->filteredPayoutAttemptsQuery()->latest('id')->paginate($this->payoutPerPage, ['*'], 'payoutPage')
            : $this->emptyPaginator($this->payoutPerPage, 'payoutPage');

        $webhookEvents = $this->readyToLoad && $this->showPipeline('webhook')
            ? $this->filteredWebhookEventsQuery()->latest('id')->paginate($this->webhookPerPage, ['*'], 'webhookPage')
            : $this->emptyPaginator($this->webhookPerPage, 'webhookPage');

        $autoRecoveryRuns = $this->readyToLoad
            ? $this->autoRecoveryRunsQuery()->latest('event_at')->latest('id')->paginate($this->recoveryRunsPerPage, ['*'], 'recoveryRunsPage')
            : $this->emptyPaginator($this->recoveryRunsPerPage, 'recoveryRunsPage');

        $alertSummaries = $this->readyToLoad
            ? $this->alertSummariesQuery()->latest('event_at')->latest('id')->paginate($this->alertSummariesPerPage, ['*'], 'alertSummariesPage')
            : $this->emptyPaginator($this->alertSummariesPerPage, 'alertSummariesPage');

        return view('livewire.platform.execution-operations-page', [
            'tenantOptions' => $tenantOptions,
            'providerOptions' => $providerOptions,
            'stats' => $stats,
            'billingAttempts' => $billingAttempts,
            'payoutAttempts' => $payoutAttempts,
            'webhookEvents' => $webhookEvents,
            'autoRecoveryRuns' => $autoRecoveryRuns,
            'alertSummaries' => $alertSummaries,
        ]);
    }

    private function resetPagination(): void
    {
        $this->resetPage('billingPage');
        $this->resetPage('payoutPage');
        $this->resetPage('webhookPage');
        $this->resetPage('recoveryRunsPage');
        $this->resetPage('alertSummariesPage');
    }

    private function showPipeline(string $pipeline): bool
    {
        return in_array($this->pipelineFilter, ['all', $pipeline], true);
    }

    private function billingAttemptsBaseQuery()
    {
        return TenantSubscriptionBillingAttempt::query()
            ->with(['company:id,name'])
            ->whereIn('company_id', $this->tenantCompanyIds());
    }

    private function payoutAttemptsBaseQuery()
    {
        return RequestPayoutExecutionAttempt::query()
            ->with(['company:id,name', 'request:id,request_code'])
            ->whereIn('company_id', $this->tenantCompanyIds());
    }

    private function webhookEventsBaseQuery()
    {
        return ExecutionWebhookEvent::query()
            ->with(['company:id,name'])
            ->whereIn('company_id', $this->tenantCompanyIds());
    }

    private function filteredBillingAttemptsQuery()
    {
        $query = $this->billingAttemptsBaseQuery();

        $this->applyCommonFilters($query, 'provider_key');

        if ($this->statusFilter !== 'all') {
            $allowed = ['queued', 'processing', 'webhook_pending', 'settled', 'failed', 'skipped', 'reversed'];
            if (in_array($this->statusFilter, $allowed, true)) {
                $query->where('attempt_status', $this->statusFilter);
            }
        }

        return $query;
    }

    private function filteredPayoutAttemptsQuery()
    {
        $query = $this->payoutAttemptsBaseQuery();

        $this->applyCommonFilters($query, 'provider_key');

        if ($this->statusFilter !== 'all') {
            $allowed = ['queued', 'processing', 'webhook_pending', 'settled', 'failed', 'skipped', 'reversed'];
            if (in_array($this->statusFilter, $allowed, true)) {
                $query->where('execution_status', $this->statusFilter);
            }
        }

        return $query;
    }

    private function filteredWebhookEventsQuery()
    {
        $query = $this->webhookEventsBaseQuery();

        $this->applyCommonFilters($query, 'provider_key');

        if ($this->statusFilter !== 'all') {
            if ($this->statusFilter === 'invalid') {
                $query->where('verification_status', 'invalid');
            } elseif (in_array($this->statusFilter, ['queued', 'processed', 'ignored', 'failed'], true)) {
                $query->where('processing_status', $this->statusFilter);
            }
        }

        return $query;
    }


    private function autoRecoveryRunsQuery()
    {
        // Auto-recovery summaries are persisted as tenant audit events for lightweight reporting.
        $query = TenantAuditEvent::query()
            ->with(['company:id,name'])
            ->where('action', 'tenant.execution.auto_recovery.run_summary')
            ->whereIn('company_id', $this->tenantCompanyIds());

        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        if ($this->pipelineFilter !== 'all') {
            $query->where('metadata->pipeline', $this->pipelineFilter);
        }

        if ($this->providerFilter !== 'all') {
            $query->where('metadata->provider_key', $this->providerFilter);
        }

        return $query;
    }

    private function alertSummariesQuery()
    {
        // Alert summaries come from scheduled execution:ops:alert-summary runs.
        $query = TenantAuditEvent::query()
            ->with(['company:id,name'])
            ->where('action', 'tenant.execution.alert.summary_emitted')
            ->whereIn('company_id', $this->tenantCompanyIds());

        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        if ($this->pipelineFilter !== 'all') {
            $query->where('metadata->pipeline', $this->pipelineFilter);
        }

        if ($this->providerFilter !== 'all') {
            $query->where('metadata->provider_key', $this->providerFilter);
        }

        return $query;
    }
    private function applyCommonFilters($query, string $providerColumn): void
    {
        $this->applyScopeFilters($query, $providerColumn);

        if ($this->onlyOlderThan) {
            $query->where('updated_at', '<=', Carbon::now()->subMinutes($this->normalizeTableOlderThanMinutes()));
        }
    }

    private function applyScopeFilters($query, string $providerColumn): void
    {
        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        if ($this->providerFilter !== 'all') {
            $query->where($providerColumn, (string) $this->providerFilter);
        }
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

    /**
     * @return array<int, string>
     */
    private function providerOptions(): array
    {
        $billingProviders = $this->billingAttemptsBaseQuery()
            ->select('provider_key')
            ->distinct()
            ->pluck('provider_key')
            ->all();

        $payoutProviders = $this->payoutAttemptsBaseQuery()
            ->select('provider_key')
            ->distinct()
            ->pluck('provider_key')
            ->all();

        $webhookProviders = $this->webhookEventsBaseQuery()
            ->select('provider_key')
            ->distinct()
            ->pluck('provider_key')
            ->all();

        $providers = array_values(array_unique(array_filter(array_merge($billingProviders, $payoutProviders, $webhookProviders))));
        sort($providers);

        return array_values($providers);
    }

    /**
     * @return array{billing_failed:int,payout_failed:int,webhook_failed:int,stuck_queued:int,incident_window_minutes:int,failure_rate_percent:float,skipped_rate_percent:float,oldest_queue_age_minutes:?int,last_recovery_outcome:?string}
     */
    private function stats(): array
    {
        $cutoff = Carbon::now()->subMinutes($this->normalizeBatchOlderThanMinutes());
        $incidentWindowMinutes = max(5, (int) config('execution.ops_alerts.window_minutes', 60));
        $windowSince = Carbon::now()->subMinutes($incidentWindowMinutes);

        $billingFailed = $this->showPipeline('billing')
            ? (clone $this->filteredBillingAttemptsQuery())->where('attempt_status', 'failed')->count()
            : 0;

        $payoutFailed = $this->showPipeline('payout')
            ? (clone $this->filteredPayoutAttemptsQuery())->where('execution_status', 'failed')->count()
            : 0;

        $webhookFailed = $this->showPipeline('webhook')
            ? (clone $this->filteredWebhookEventsQuery())
                ->where(function ($query): void {
                    $query->where('processing_status', 'failed')
                        ->orWhere('verification_status', 'invalid');
                })
                ->count()
            : 0;

        $stuckBilling = 0;
        if ($this->showPipeline('billing')) {
            $stuckBillingQuery = $this->billingAttemptsBaseQuery();
            $this->applyScopeFilters($stuckBillingQuery, 'provider_key');
            $stuckBilling = (clone $stuckBillingQuery)
                ->where('attempt_status', 'queued')
                ->where('queued_at', '<=', $cutoff)
                ->count();
        }

        $stuckPayout = 0;
        if ($this->showPipeline('payout')) {
            $stuckPayoutQuery = $this->payoutAttemptsBaseQuery();
            $this->applyScopeFilters($stuckPayoutQuery, 'provider_key');
            $stuckPayout = (clone $stuckPayoutQuery)
                ->where('execution_status', 'queued')
                ->where('queued_at', '<=', $cutoff)
                ->count();
        }

        $stuckWebhooks = 0;
        if ($this->showPipeline('webhook')) {
            $stuckWebhookQuery = $this->webhookEventsBaseQuery();
            $this->applyScopeFilters($stuckWebhookQuery, 'provider_key');
            $stuckWebhooks = (clone $stuckWebhookQuery)
                ->where('processing_status', 'queued')
                ->where('received_at', '<=', $cutoff)
                ->count();
        }

        $recentBillingTotal = $this->showPipeline('billing')
            ? (clone $this->filteredBillingAttemptsQuery())->where('updated_at', '>=', $windowSince)->count()
            : 0;
        $recentBillingFailed = $this->showPipeline('billing')
            ? (clone $this->filteredBillingAttemptsQuery())->where('updated_at', '>=', $windowSince)->where('attempt_status', 'failed')->count()
            : 0;
        $recentBillingSkipped = $this->showPipeline('billing')
            ? (clone $this->filteredBillingAttemptsQuery())->where('updated_at', '>=', $windowSince)->where('attempt_status', 'skipped')->count()
            : 0;

        $recentPayoutTotal = $this->showPipeline('payout')
            ? (clone $this->filteredPayoutAttemptsQuery())->where('updated_at', '>=', $windowSince)->count()
            : 0;
        $recentPayoutFailed = $this->showPipeline('payout')
            ? (clone $this->filteredPayoutAttemptsQuery())->where('updated_at', '>=', $windowSince)->where('execution_status', 'failed')->count()
            : 0;
        $recentPayoutSkipped = $this->showPipeline('payout')
            ? (clone $this->filteredPayoutAttemptsQuery())->where('updated_at', '>=', $windowSince)->where('execution_status', 'skipped')->count()
            : 0;

        $recentWebhookTotal = $this->showPipeline('webhook')
            ? (clone $this->filteredWebhookEventsQuery())->where('updated_at', '>=', $windowSince)->count()
            : 0;
        $recentWebhookFailed = $this->showPipeline('webhook')
            ? (clone $this->filteredWebhookEventsQuery())
                ->where('updated_at', '>=', $windowSince)
                ->where(function ($query): void {
                    $query->where('processing_status', 'failed')
                        ->orWhere('verification_status', 'invalid');
                })
                ->count()
            : 0;

        $recentTotal = $recentBillingTotal + $recentPayoutTotal + $recentWebhookTotal;
        $recentFailures = $recentBillingFailed + $recentPayoutFailed + $recentWebhookFailed;
        $failureRatePercent = $recentTotal > 0
            ? round(($recentFailures / $recentTotal) * 100, 1)
            : 0.0;

        $recentExecutionTotal = $recentBillingTotal + $recentPayoutTotal;
        $recentSkipped = $recentBillingSkipped + $recentPayoutSkipped;
        $skippedRatePercent = $recentExecutionTotal > 0
            ? round(($recentSkipped / $recentExecutionTotal) * 100, 1)
            : 0.0;

        return [
            'billing_failed' => (int) $billingFailed,
            'payout_failed' => (int) $payoutFailed,
            'webhook_failed' => (int) $webhookFailed,
            'stuck_queued' => (int) ($stuckBilling + $stuckPayout + $stuckWebhooks),
            'incident_window_minutes' => $incidentWindowMinutes,
            'failure_rate_percent' => (float) $failureRatePercent,
            'skipped_rate_percent' => (float) $skippedRatePercent,
            'oldest_queue_age_minutes' => $this->oldestQueuedAgeMinutes(),
            'last_recovery_outcome' => $this->latestRecoveryOutcome(),
        ];
    }

    private function oldestQueuedAgeMinutes(): ?int
    {
        $oldestQueuedAt = null;

        if ($this->showPipeline('billing')) {
            $query = $this->billingAttemptsBaseQuery();
            $this->applyScopeFilters($query, 'provider_key');
            $candidate = (clone $query)
                ->where('attempt_status', 'queued')
                ->whereNotNull('queued_at')
                ->orderBy('queued_at')
                ->value('queued_at');

            if ($candidate) {
                $candidateCarbon = Carbon::parse((string) $candidate);
                $oldestQueuedAt = $oldestQueuedAt instanceof Carbon
                    ? ($candidateCarbon->lt($oldestQueuedAt) ? $candidateCarbon : $oldestQueuedAt)
                    : $candidateCarbon;
            }
        }

        if ($this->showPipeline('payout')) {
            $query = $this->payoutAttemptsBaseQuery();
            $this->applyScopeFilters($query, 'provider_key');
            $candidate = (clone $query)
                ->where('execution_status', 'queued')
                ->whereNotNull('queued_at')
                ->orderBy('queued_at')
                ->value('queued_at');

            if ($candidate) {
                $candidateCarbon = Carbon::parse((string) $candidate);
                $oldestQueuedAt = $oldestQueuedAt instanceof Carbon
                    ? ($candidateCarbon->lt($oldestQueuedAt) ? $candidateCarbon : $oldestQueuedAt)
                    : $candidateCarbon;
            }
        }

        if ($this->showPipeline('webhook')) {
            $query = $this->webhookEventsBaseQuery();
            $this->applyScopeFilters($query, 'provider_key');
            $candidate = (clone $query)
                ->where('processing_status', 'queued')
                ->whereNotNull('received_at')
                ->orderBy('received_at')
                ->value('received_at');

            if ($candidate) {
                $candidateCarbon = Carbon::parse((string) $candidate);
                $oldestQueuedAt = $oldestQueuedAt instanceof Carbon
                    ? ($candidateCarbon->lt($oldestQueuedAt) ? $candidateCarbon : $oldestQueuedAt)
                    : $candidateCarbon;
            }
        }

        if (! $oldestQueuedAt instanceof Carbon) {
            return null;
        }

        return max(0, (int) floor($oldestQueuedAt->diffInMinutes(Carbon::now())));
    }

    private function latestRecoveryOutcome(): ?string
    {
        $actions = [
            'tenant.execution.billing.process_stuck_queued',
            'tenant.execution.payout.process_stuck_queued',
            'tenant.execution.billing.auto_recovered_queued',
            'tenant.execution.payout.auto_recovered_queued',
            'tenant.execution.webhook.auto_recovered_queued',
            'tenant.execution.auto_recovery.run_summary',
            'tenant.execution.webhook.manual_reconciled_billing',
            'tenant.execution.webhook.manual_reconciled_payout',
        ];

        $query = TenantAuditEvent::query()
            ->whereIn('company_id', $this->tenantCompanyIds())
            ->whereIn('action', $actions);

        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        $latest = $query->latest('event_at')->latest('id')->first(['action', 'event_at']);
        if (! $latest) {
            return null;
        }

        $label = $this->recoveryActionLabel((string) $latest->action);
        $timestamp = $latest->event_at?->format('M d, H:i') ?? 'unknown time';

        return $label.' on '.$timestamp;
    }

    private function recoveryActionLabel(string $action): string
    {
        return match ($action) {
            'tenant.execution.billing.process_stuck_queued' => 'Billing manual recovery',
            'tenant.execution.payout.process_stuck_queued' => 'Payout manual recovery',
            'tenant.execution.billing.auto_recovered_queued' => 'Billing auto recovery',
            'tenant.execution.payout.auto_recovered_queued' => 'Payout auto recovery',
            'tenant.execution.webhook.auto_recovered_queued' => 'Webhook auto recovery',
            'tenant.execution.auto_recovery.run_summary' => 'Auto recovery summary',
            'tenant.execution.webhook.manual_reconciled_billing' => 'Webhook manual reconcile (billing)',
            'tenant.execution.webhook.manual_reconciled_payout' => 'Webhook manual reconcile (payout)',
            default => 'Execution recovery',
        };
    }

    /**
     * @param  array<string,mixed>  $metadata
     * @return array<string,mixed>
     */
    private function appendManualActionMetadata(array $metadata, string $action, string $reason, int $actorId): array
    {
        $history = (array) ($metadata['manual_actions'] ?? []);
        $history[] = [
            'action' => $action,
            'reason' => $reason,
            'actor_user_id' => $actorId > 0 ? $actorId : null,
            'at' => now()->toDateTimeString(),
        ];

        $metadata['manual_actions'] = array_slice($history, -25);

        return $metadata;
    }

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => $pageName,
        ]);
    }


    private function normalizeTableOlderThanMinutes(): int
    {
        $value = (int) trim((string) $this->tableOlderThanMinutes);

        if ($value < 1) {
            return 1;
        }

        if ($value > 43200) {
            return 43200;
        }

        return $value;
    }

    private function normalizeBatchOlderThanMinutes(): int
    {
        $value = (int) trim((string) $this->batchOlderThanMinutes);

        if ($value < 1) {
            return 1;
        }

        if ($value > 43200) {
            return 43200;
        }

        return $value;
    }
    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }
}












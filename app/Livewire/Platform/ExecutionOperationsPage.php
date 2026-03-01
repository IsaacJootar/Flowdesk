<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
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
#[Title('Execution Operations')]
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

    public int $olderThanMinutes = 30;

    public int $billingPerPage = 10;

    public int $payoutPerPage = 10;

    public int $webhookPerPage = 10;

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

    public function updatedOlderThanMinutes(): void
    {
        if ($this->olderThanMinutes < 1) {
            $this->olderThanMinutes = 1;
        }

        if ($this->olderThanMinutes > 43200) {
            $this->olderThanMinutes = 43200;
        }

        $this->resetPagination();
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
            'olderThanMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
        ]);

        $cutoff = Carbon::now()->subMinutes((int) $this->olderThanMinutes);
        $processor = app(SubscriptionBillingAttemptProcessor::class);
        $actor = Auth::user();

        $processed = 0;

        $this->billingAttemptsBaseQuery()
            ->where('attempt_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (TenantSubscriptionBillingAttempt $attempt) use ($processor, $reason, $actor, &$processed): void {
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
                }
            });

        $this->setFeedback($processed > 0
            ? 'Processed '.$processed.' stuck queued billing attempts.'
            : 'No queued billing attempts matched the selected age window.');
    }

    public function processStuckPayoutQueued(): void
    {
        $this->authorizePlatformOperator();
        $reason = trim($this->batchReason);

        $this->validate([
            'batchReason' => ['required', 'string', 'min:4', 'max:500'],
            'olderThanMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
        ]);

        $cutoff = Carbon::now()->subMinutes((int) $this->olderThanMinutes);
        $processor = app(RequestPayoutExecutionAttemptProcessor::class);
        $actor = Auth::user();

        $processed = 0;

        $this->payoutAttemptsBaseQuery()
            ->where('execution_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (RequestPayoutExecutionAttempt $attempt) use ($processor, $reason, $actor, &$processed): void {
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
                }
            });

        $this->setFeedback($processed > 0
            ? 'Processed '.$processed.' stuck queued payout attempts.'
            : 'No queued payout attempts matched the selected age window.');
    }

    public function processStuckWebhookQueue(): void
    {
        $this->authorizePlatformOperator();

        $this->validate([
            'batchReason' => ['required', 'string', 'min:4', 'max:500'],
            'olderThanMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
        ]);

        $cutoff = Carbon::now()->subMinutes((int) $this->olderThanMinutes);
        $service = app(ExecutionWebhookManualReconciliationService::class);
        $actor = Auth::user();
        $reason = trim($this->batchReason);

        $processed = 0;

        $this->webhookEventsBaseQuery()
            ->where('processing_status', 'queued')
            ->where('received_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (ExecutionWebhookEvent $event) use ($service, $actor, $reason, &$processed): void {
                $result = $service->reconcile($event, $reason, $actor);
                if ($result['ok']) {
                    $processed++;
                }
            });

        $this->setFeedback($processed > 0
            ? 'Processed '.$processed.' stuck queued webhook events.'
            : 'No queued webhook events matched the selected age window.');
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

        return view('livewire.platform.execution-operations-page', [
            'tenantOptions' => $tenantOptions,
            'providerOptions' => $providerOptions,
            'stats' => $stats,
            'billingAttempts' => $billingAttempts,
            'payoutAttempts' => $payoutAttempts,
            'webhookEvents' => $webhookEvents,
        ]);
    }

    private function resetPagination(): void
    {
        $this->resetPage('billingPage');
        $this->resetPage('payoutPage');
        $this->resetPage('webhookPage');
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

    private function applyCommonFilters($query, string $providerColumn): void
    {
        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        if ($this->providerFilter !== 'all') {
            $query->where($providerColumn, (string) $this->providerFilter);
        }

        if ($this->onlyOlderThan) {
            $query->where('updated_at', '<=', Carbon::now()->subMinutes((int) $this->olderThanMinutes));
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
     * @return array{billing_failed:int,payout_failed:int,webhook_failed:int,stuck_queued:int}
     */
    private function stats(): array
    {
        $cutoff = Carbon::now()->subMinutes((int) $this->olderThanMinutes);

        $billingFailed = (clone $this->filteredBillingAttemptsQuery())
            ->where('attempt_status', 'failed')
            ->count();

        $payoutFailed = (clone $this->filteredPayoutAttemptsQuery())
            ->where('execution_status', 'failed')
            ->count();

        $webhookFailed = (clone $this->filteredWebhookEventsQuery())
            ->where(function ($query): void {
                $query->where('processing_status', 'failed')
                    ->orWhere('verification_status', 'invalid');
            })
            ->count();

        $stuckBilling = (clone $this->billingAttemptsBaseQuery())
            ->where('attempt_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->count();

        $stuckPayout = (clone $this->payoutAttemptsBaseQuery())
            ->where('execution_status', 'queued')
            ->where('queued_at', '<=', $cutoff)
            ->count();

        $stuckWebhooks = (clone $this->webhookEventsBaseQuery())
            ->where('processing_status', 'queued')
            ->where('received_at', '<=', $cutoff)
            ->count();

        return [
            'billing_failed' => (int) $billingFailed,
            'payout_failed' => (int) $payoutFailed,
            'webhook_failed' => (int) $webhookFailed,
            'stuck_queued' => (int) ($stuckBilling + $stuckPayout + $stuckWebhooks),
        ];
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


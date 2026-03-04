<?php

namespace App\Livewire\Execution;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Execution\RequestPayoutExecutionAttemptProcessor;
use App\Services\Execution\RequestPayoutExecutionOrchestrator;
use App\Services\PlatformAccessService;
use App\Services\TenantAuditLogger;
use App\Services\TenantExecutionModeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payout Ready Queue')]
class PayoutReadyQueuePage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $statusFilter = 'all';

    public string $search = '';

    public int $perPage = 12;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public function mount(): void
    {
        abort_unless($this->canAccessPage(), 403);

        $deepLinkSearch = trim((string) request()->query('search', ''));
        if ($deepLinkSearch !== '') {
            $this->search = mb_substr($deepLinkSearch, 0, 120);
        }
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
    }

    public function updatedStatusFilter(): void
    {
        $allowed = ['all', 'ready', 'queued', 'processing', 'failed'];
        if (! in_array($this->statusFilter, $allowed, true)) {
            $this->statusFilter = 'all';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function runPayoutNow(int $requestId): void
    {
        abort_unless($this->canRunPayoutActions(), 403);

        $request = $this->queueBaseQuery()
            ->whereKey($requestId)
            ->first();

        if (! $request) {
            $this->setFeedbackError('Request is no longer available in your tenant scope.');

            return;
        }

        [$ok, $message] = $this->executeRequestPayout($request, Auth::user());

        if ($ok) {
            $this->setFeedback($message);

            return;
        }

        $this->setFeedbackError($message);
    }

    public function render(): View
    {
        $summary = $this->readyToLoad ? $this->summary() : $this->emptySummary();

        $rows = $this->readyToLoad
            ? $this->queueBaseQuery()->paginate($this->perPage, ['*'], 'queuePage')
            : $this->emptyPaginator();

        return view('livewire.execution.payout-ready-queue-page', [
            'summary' => $summary,
            'rows' => $rows,
            'canRunPayoutActions' => $this->canRunPayoutActions(),
        ]);
    }

    public function pipelineCondition(SpendRequest $request): string
    {
        $attempt = $request->payoutExecutionAttempt;

        if (! $attempt) {
            if ((bool) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.blocked', false)) {
                return 'Blocked by procurement gate: '.trim((string) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.reason', 'Review procurement match requirements.'));
            }

            $subscription = $request->company?->subscription;
            if (! $subscription) {
                return 'No tenant subscription found for execution handoff.';
            }

            if ((string) $subscription->payment_execution_mode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED) {
                return 'Execution mode is decision-only for this tenant.';
            }

            if (trim((string) $subscription->execution_provider) === '') {
                return 'Execution provider is not configured.';
            }

            return 'Ready to queue payout execution.';
        }

        return match ((string) $attempt->execution_status) {
            'queued' => 'Queued and waiting for processing.',
            'processing' => 'Currently processing.',
            'webhook_pending' => 'Waiting for provider webhook confirmation.',
            'failed' => 'Failed: '.trim((string) ($attempt->error_message ?: 'Check provider/config/state and retry.')),
            'skipped' => 'Skipped by no-op provider configuration.',
            default => 'Execution state: '.str_replace('_', ' ', (string) $attempt->execution_status).'.',
        };
    }

    public function finalApproverName(SpendRequest $request): string
    {
        $approval = $request->approvals->first();
        if (! $approval instanceof RequestApproval) {
            return '-';
        }

        return (string) ($approval->actor?->name ?: 'System');
    }

    /**
     * @return array{total:int,ready:int,queued:int,processing:int,failed:int}
     */
    private function summary(): array
    {
        $query = $this->baseRequestQuery();

        return [
            'total' => (int) (clone $query)->count(),
            'ready' => (int) (clone $query)->where('status', 'approved_for_execution')->count(),
            'queued' => (int) (clone $query)->where('status', 'execution_queued')->count(),
            'processing' => (int) (clone $query)->where('status', 'execution_processing')->count(),
            'failed' => (int) (clone $query)->where('status', 'failed')->count(),
        ];
    }

    private function canAccessPage(): bool
    {
        $user = Auth::user();
        if (! $user || app(PlatformAccessService::class)->isPlatformOperator($user)) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canRunPayoutActions(): bool
    {
        $user = Auth::user();
        if (! $user || app(PlatformAccessService::class)->isPlatformOperator($user)) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
        ], true);
    }

    private function companyId(): int
    {
        return (int) (Auth::user()?->company_id ?? 0);
    }

    private function baseRequestQuery(): Builder
    {
        return SpendRequest::query()
            ->where('company_id', $this->companyId())
            ->whereIn('status', [
                'approved_for_execution',
                'execution_queued',
                'execution_processing',
                'failed',
            ]);
    }

    private function queueBaseQuery(): Builder
    {
        $query = $this->baseRequestQuery()
            ->with([
                'company.subscription:id,company_id,payment_execution_mode,execution_provider',
                'requester:id,name',
                // Latest approved action indicates the final approver in the chain.
                'approvals' => function ($approvalQuery): void {
                    $approvalQuery
                        ->where('action', 'approved')
                        ->whereNotNull('acted_by')
                        ->with('actor:id,name')
                        ->latest('acted_at')
                        ->latest('id');
                },
                'payoutExecutionAttempt:id,company_id,request_id,execution_status,queued_at,processed_at,settled_at,error_message,error_code',
            ])
            ->orderByRaw("CASE status WHEN 'failed' THEN 1 WHEN 'approved_for_execution' THEN 2 WHEN 'execution_queued' THEN 3 WHEN 'execution_processing' THEN 4 ELSE 99 END")
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($this->statusFilter !== 'all') {
            $status = match ($this->statusFilter) {
                'ready' => 'approved_for_execution',
                'queued' => 'execution_queued',
                'processing' => 'execution_processing',
                'failed' => 'failed',
                default => null,
            };

            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * @return array{0:bool,1:string}
     */
    private function executeRequestPayout(SpendRequest $request, ?User $actor): array
    {
        $actorId = (int) ($actor?->id ?? 0);
        $request->loadMissing(['company.subscription', 'payoutExecutionAttempt.subscription']);

        $attempt = $request->payoutExecutionAttempt;

        if (! $attempt) {
            if ((string) $request->status !== 'approved_for_execution') {
                return [false, 'Request is not in a payout-ready state. Refresh and try again.'];
            }

            $attempt = app(RequestPayoutExecutionOrchestrator::class)->queueForApprovedRequest($request, $actorId > 0 ? $actorId : null);

            if (! $attempt) {
                return [false, $this->queueBlockedMessage($request->fresh())];
            }
        }

        if (in_array((string) $attempt->execution_status, ['settled', 'reversed'], true)) {
            return [false, 'This request is already completed and no longer in payout queue.'];
        }

        if (in_array((string) $attempt->execution_status, ['failed', 'skipped'], true)) {
            $attempt->forceFill([
                'execution_status' => 'queued',
                'queued_at' => now(),
                'failed_at' => null,
                'next_retry_at' => null,
                'error_code' => null,
                'error_message' => null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ])->save();
        }

        if ((string) $attempt->execution_status === 'processing') {
            return [false, 'This payout is already processing.'];
        }

        $processed = app(RequestPayoutExecutionAttemptProcessor::class)->processAttemptById((int) $attempt->id);

        if (! $processed) {
            return [false, 'Found payout-ready request, but none was processed. Check provider/config/state and retry.'];
        }

        $attempt->refresh();
        $request->refresh();

        app(TenantAuditLogger::class)->log(
            companyId: (int) $request->company_id,
            action: 'tenant.execution.payout.manual_queue_run',
            actor: $actor,
            description: 'Manual payout run executed from tenant payout queue.',
            entityType: SpendRequest::class,
            entityId: (int) $request->id,
            metadata: [
                'request_code' => (string) $request->request_code,
                'payout_attempt_id' => (int) $attempt->id,
                'final_execution_status' => (string) $attempt->execution_status,
            ],
        );

        $status = (string) $attempt->execution_status;
        $label = str_replace('_', ' ', $status);

        if ($status === 'settled') {
            return [true, 'Processed payout for '.(string) $request->request_code.'. Outcome: settled.'];
        }

        if ($status === 'failed') {
            return [false, 'Payout run executed for '.(string) $request->request_code.', but outcome is failed. Check provider/config/state and retry.'];
        }

        if ($status === 'skipped') {
            return [true, 'Processed payout for '.(string) $request->request_code.'. Outcome: skipped (no-op provider).'];
        }

        return [true, 'Processed payout for '.(string) $request->request_code.'. Outcome: '.$label.'.'];
    }

    private function queueBlockedMessage(?SpendRequest $request): string
    {
        if (! $request) {
            return 'Payout could not be queued. Check provider/config/state and retry.';
        }

        $metadata = (array) ($request->metadata ?? []);
        if ((bool) data_get($metadata, 'execution.procurement_gate.blocked', false)) {
            $reason = trim((string) data_get($metadata, 'execution.procurement_gate.reason', 'Procurement gate blocked payout queueing.'));

            return 'Payout was not queued. '.$reason;
        }

        $subscription = $request->company?->subscription;
        if (! $subscription) {
            return 'Payout was not queued. Tenant subscription is missing.';
        }

        if ((string) $subscription->payment_execution_mode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED) {
            return 'Payout was not queued. Tenant is in decision-only mode.';
        }

        if (trim((string) $subscription->execution_provider) === '') {
            return 'Payout was not queued. Execution provider is not configured.';
        }

        return 'Payout could not be queued. Check provider/config/state and retry.';
    }

    /**
     * @return array{total:int,ready:int,queued:int,processing:int,failed:int}
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'ready' => 0,
            'queued' => 0,
            'processing' => 0,
            'failed' => 0,
        ];
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $this->perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => 'queuePage',
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

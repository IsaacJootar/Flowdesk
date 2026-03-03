<?php

namespace App\Livewire\Treasury;

use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Treasury Reconciliation Exceptions')]
class TreasuryReconciliationExceptionsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $statusFilter = 'open';

    public string $severityFilter = 'all';

    public string $streamFilter = 'all';

    public string $search = '';

    public int $perPage = 10;

    public bool $showResolutionModal = false;

    public ?int $selectedExceptionId = null;

    public string $resolutionAction = 'resolved';

    public string $resolutionNotes = '';

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSeverityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStreamFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openResolutionModal(int $exceptionId, string $action): void
    {
        if (! in_array($action, ['resolved', 'waived'], true)) {
            return;
        }

        $this->selectedExceptionId = $exceptionId;
        $this->resolutionAction = $action;
        $this->resolutionNotes = '';
        $this->showResolutionModal = true;
    }

    public function closeResolutionModal(): void
    {
        $this->showResolutionModal = false;
        $this->selectedExceptionId = null;
        $this->resolutionAction = 'resolved';
        $this->resolutionNotes = '';
    }

    public function applyResolution(TenantAuditLogger $tenantAuditLogger): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperate($user)) {
            $this->setFeedbackError('Only owner/finance can resolve treasury exceptions.');

            return;
        }

        if (! $this->selectedExceptionId) {
            $this->setFeedbackError('Select an exception to resolve first.');

            return;
        }

        $validated = $this->validate([
            'resolutionNotes' => ['required', 'string', 'max:2000'],
        ]);

        $exception = ReconciliationException::query()->with('line')->findOrFail((int) $this->selectedExceptionId);
        if ((int) $exception->company_id !== (int) $user->company_id) {
            $this->setFeedbackError('Exception is outside your tenant scope.');

            return;
        }

        if ((string) $exception->exception_status !== ReconciliationException::STATUS_OPEN) {
            $this->setFeedbackError('Exception is already closed.');

            return;
        }

        $newStatus = $this->resolutionAction === 'waived'
            ? ReconciliationException::STATUS_WAIVED
            : ReconciliationException::STATUS_RESOLVED;

        $exception->forceFill([
            'exception_status' => $newStatus,
            'resolution_notes' => (string) $validated['resolutionNotes'],
            'resolved_at' => now(),
            'resolved_by_user_id' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ])->save();

        $line = $exception->line;
        if ($line instanceof BankStatementLine) {
            $remainingOpen = ReconciliationException::query()
                ->where('bank_statement_line_id', (int) $line->id)
                ->where('exception_status', ReconciliationException::STATUS_OPEN)
                ->count();

            if ($remainingOpen === 0) {
                $line->forceFill([
                    'is_reconciled' => true,
                    'reconciled_at' => now(),
                    'updated_by' => (int) $user->id,
                ])->save();
            }
        }

        $tenantAuditLogger->log(
            companyId: (int) $user->company_id,
            action: $newStatus === ReconciliationException::STATUS_WAIVED
                ? 'tenant.treasury.exception.waived'
                : 'tenant.treasury.exception.resolved',
            actor: $user,
            description: 'Treasury reconciliation exception updated from workbench.',
            entityType: ReconciliationException::class,
            entityId: (int) $exception->id,
            metadata: [
                'exception_code' => (string) $exception->exception_code,
                'match_stream' => (string) $exception->match_stream,
                'new_status' => $newStatus,
            ],
        );

        $this->closeResolutionModal();
        $this->setFeedback('Treasury exception updated.');
    }

    public function render(): View
    {
        $query = ReconciliationException::query()
            ->with('line:id,line_reference,description,amount,currency_code,posted_at')
            ->where('company_id', (int) auth()->user()->company_id)
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('exception_status', $this->statusFilter))
            ->when($this->severityFilter !== 'all', fn ($builder) => $builder->where('severity', $this->severityFilter))
            ->when($this->streamFilter !== 'all', fn ($builder) => $builder->where('match_stream', $this->streamFilter))
            ->when($this->search !== '', function ($builder): void {
                $builder->where(function ($inner): void {
                    $inner->where('exception_code', 'like', '%'.$this->search.'%')
                        ->orWhere('details', 'like', '%'.$this->search.'%')
                        ->orWhereHas('line', fn ($lineQuery) => $lineQuery->where('line_reference', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('id');

        $exceptions = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : ReconciliationException::query()->whereRaw('1=0')->paginate($this->perPage);

        $summary = $this->readyToLoad
            ? [
                'open' => (clone $query)->where('exception_status', ReconciliationException::STATUS_OPEN)->count(),
                'closed' => (clone $query)->whereIn('exception_status', [ReconciliationException::STATUS_RESOLVED, ReconciliationException::STATUS_WAIVED])->count(),
                'critical' => (clone $query)->whereIn('severity', [ReconciliationException::SEVERITY_HIGH, ReconciliationException::SEVERITY_CRITICAL])->count(),
            ]
            : ['open' => 0, 'closed' => 0, 'critical' => 0];

        return view('livewire.treasury.treasury-reconciliation-exceptions-page', [
            'exceptions' => $exceptions,
            'summary' => $summary,
            'statuses' => ['all', ReconciliationException::STATUS_OPEN, ReconciliationException::STATUS_RESOLVED, ReconciliationException::STATUS_WAIVED],
            'severities' => ['all', ReconciliationException::SEVERITY_LOW, ReconciliationException::SEVERITY_MEDIUM, ReconciliationException::SEVERITY_HIGH, ReconciliationException::SEVERITY_CRITICAL],
            'streams' => ['all', ReconciliationException::STREAM_EXECUTION_PAYMENT, ReconciliationException::STREAM_EXPENSE_EVIDENCE, ReconciliationException::STREAM_REIMBURSEMENT],
            'canOperate' => auth()->check() && $this->canOperate(auth()->user()),
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

    private function canAccessPage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canOperate(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }
}
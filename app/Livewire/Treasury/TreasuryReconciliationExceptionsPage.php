<?php

namespace App\Livewire\Treasury;

use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\TreasuryControlSettingsService;
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

    public string $queueSort = 'priority';

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

    public function updatedQueueSort(): void
    {
        if (! in_array($this->queueSort, ['priority', 'newest', 'oldest'], true)) {
            $this->queueSort = 'priority';
        }

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

    public function applyResolution(
        TenantAuditLogger $tenantAuditLogger,
        TreasuryControlSettingsService $treasuryControlSettingsService
    ): void {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $controls = $treasuryControlSettingsService->effectiveControls((int) $user->company_id);
        $allowedRoles = $this->normalizeRoles((array) ($controls['exception_action_allowed_roles'] ?? ['owner', 'finance']));
        $requiresMakerChecker = (bool) ($controls['exception_action_requires_maker_checker'] ?? true);

        if (! $this->canOperate($user, $allowedRoles)) {
            $this->setFeedbackError(sprintf('Only [%s] can resolve or waive treasury exceptions.', implode(', ', $allowedRoles)));

            $tenantAuditLogger->log(
                companyId: (int) $user->company_id,
                action: 'tenant.treasury.exception.action.denied',
                actor: $user,
                description: 'Treasury exception action denied by role guardrail policy.',
                entityType: ReconciliationException::class,
                metadata: [
                    'reason' => 'role_not_allowed',
                    'selected_exception_id' => $this->selectedExceptionId,
                    'allowed_roles' => $allowedRoles,
                ],
            );

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

        if ($requiresMakerChecker) {
            $makerIds = array_values(array_filter([
                (int) ($exception->created_by ?? 0),
                (int) ($exception->updated_by ?? 0),
            ]));

            // Control intent: exception maker and checker must be different for sensitive closure actions.
            if (in_array((int) $user->id, $makerIds, true)) {
                $this->setFeedbackError('Maker-checker policy requires another authorized user to resolve or waive this exception.');

                $tenantAuditLogger->log(
                    companyId: (int) $user->company_id,
                    action: 'tenant.treasury.exception.action.denied',
                    actor: $user,
                    description: 'Treasury exception action denied by maker-checker policy.',
                    entityType: ReconciliationException::class,
                    entityId: (int) $exception->id,
                    metadata: [
                        'reason' => 'maker_checker_same_user',
                        'allowed_roles' => $allowedRoles,
                        'requires_maker_checker' => true,
                    ],
                );

                return;
            }
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
                'allowed_roles' => $allowedRoles,
                'requires_maker_checker' => $requiresMakerChecker,
            ],
        );

        $this->closeResolutionModal();
        $this->setFeedback('Treasury exception updated.');
    }

    public function render(TreasuryControlSettingsService $treasuryControlSettingsService): View
    {
        $companyId = (int) auth()->user()->company_id;
        $controls = $treasuryControlSettingsService->effectiveControls($companyId);
        $slaHours = (int) ($controls['exception_alert_age_hours'] ?? 48);
        $allowedRoles = $this->normalizeRoles((array) ($controls['exception_action_allowed_roles'] ?? ['owner', 'finance']));
        $makerCheckerRequired = (bool) ($controls['exception_action_requires_maker_checker'] ?? true);

        $query = ReconciliationException::query()
            ->with('line:id,line_reference,description,amount,currency_code,posted_at')
            ->where('company_id', $companyId)
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('exception_status', $this->statusFilter))
            ->when($this->severityFilter !== 'all', fn ($builder) => $builder->where('severity', $this->severityFilter))
            ->when($this->streamFilter !== 'all', fn ($builder) => $builder->where('match_stream', $this->streamFilter))
            ->when($this->search !== '', function ($builder): void {
                $builder->where(function ($inner): void {
                    $inner->where('exception_code', 'like', '%'.$this->search.'%')
                        ->orWhere('details', 'like', '%'.$this->search.'%')
                        ->orWhereHas('line', fn ($lineQuery) => $lineQuery->where('line_reference', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%'));
                });
            });

        // Control intent: queue urgent financial risk first (open + severe + old + high value) to reduce settlement exposure.
        if ($this->queueSort === 'priority') {
            $query
                ->orderByRaw("CASE WHEN exception_status = 'open' THEN 0 ELSE 1 END ASC")
                ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
                ->orderBy('created_at')
                ->orderByRaw('COALESCE((SELECT ABS(amount) FROM bank_statement_lines WHERE bank_statement_lines.id = reconciliation_exceptions.bank_statement_line_id), 0) DESC')
                ->orderByDesc('id');
        } elseif ($this->queueSort === 'oldest') {
            $query->oldest('created_at')->oldest('id');
        } else {
            $query->latest('created_at')->latest('id');
        }

        $exceptions = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : ReconciliationException::query()->whereRaw('1=0')->paginate($this->perPage);

        if ($this->readyToLoad) {
            $exceptions->getCollection()->transform(function (ReconciliationException $exception) use ($slaHours): ReconciliationException {
                return $this->decorateExceptionForQueue($exception, $slaHours);
            });
        }

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
            'queueSortOptions' => [
                'priority' => 'Priority First',
                'newest' => 'Newest First',
                'oldest' => 'Oldest First',
            ],
            'slaHours' => $slaHours,
            'exceptionActionAllowedRoles' => $allowedRoles,
            'makerCheckerRequired' => $makerCheckerRequired,
            'canOperate' => auth()->check() && $this->canOperate(auth()->user(), $allowedRoles),
        ]);
    }

    private function decorateExceptionForQueue(ReconciliationException $exception, int $slaHours): ReconciliationException
    {
        $ageHours = $exception->created_at ? (int) $exception->created_at->diffInHours(now()) : 0;
        $lineAmount = abs((int) ($exception->line?->amount ?? 0));
        $isOpen = (string) $exception->exception_status === ReconciliationException::STATUS_OPEN;
        $isSlaBreached = $isOpen && $ageHours >= $slaHours;

        $priorityBand = 'low';
        if (! $isOpen) {
            $priorityBand = 'closed';
        } elseif (
            (string) $exception->severity === ReconciliationException::SEVERITY_CRITICAL
            || $ageHours >= ($slaHours * 2)
            || $lineAmount >= 1_000_000
        ) {
            $priorityBand = 'urgent';
        } elseif (
            (string) $exception->severity === ReconciliationException::SEVERITY_HIGH
            || $isSlaBreached
            || $lineAmount >= 500_000
        ) {
            $priorityBand = 'high';
        } elseif (
            (string) $exception->severity === ReconciliationException::SEVERITY_MEDIUM
            || $ageHours >= (int) floor(max(1, $slaHours) / 2)
            || $lineAmount >= 100_000
        ) {
            $priorityBand = 'medium';
        }

        $exception->setAttribute('age_hours', $ageHours);
        $exception->setAttribute('line_amount_abs', $lineAmount);
        $exception->setAttribute('sla_hours', $slaHours);
        $exception->setAttribute('sla_breached', $isSlaBreached);
        $exception->setAttribute('priority_band', $priorityBand);

        return $exception;
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

    /**
     * @param  array<int, string>  $allowedRoles
     */
    private function canOperate(User $user, array $allowedRoles): bool
    {
        return in_array(strtolower((string) $user->role), $this->normalizeRoles($allowedRoles), true);
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return array<int, string>
     */
    private function normalizeRoles(array $roles): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            $roles
        )));
    }
}

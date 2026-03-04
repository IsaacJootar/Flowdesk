<?php

namespace App\Livewire\Treasury;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\PaymentRun;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\AutoReconcileStatementService;
use App\Services\Treasury\ImportBankStatementCsvService;
use App\Services\Treasury\TreasuryControlSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Treasury Daily Reconciliation Desk')]
class TreasuryReconciliationPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $readyToLoad = false;

    public ?int $selectedBankAccountId = null;

    public ?int $selectedStatementId = null;

    public ?string $lineSearch = null;

    public int $linePerPage = 10;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var mixed */
    public $statementFile;

    public bool $showResolutionModal = false;

    public ?int $selectedExceptionId = null;

    public string $resolutionAction = 'resolved';

    public string $resolutionNotes = '';

    /**
     * @var array{account_name:string,bank_name:string,account_reference:string,account_number_masked:string,currency_code:string,is_primary:bool}
     */
    public array $bankAccountForm = [
        'account_name' => '',
        'bank_name' => '',
        'account_reference' => '',
        'account_number_masked' => '',
        'currency_code' => 'NGN',
        'is_primary' => false,
    ];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedLineSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLinePerPage(): void
    {
        if (! in_array($this->linePerPage, [10, 25, 50], true)) {
            $this->linePerPage = 10;
        }

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

    public function createBankAccount(TenantAuditLogger $tenantAuditLogger): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperateDesk($user)) {
            $this->setFeedbackError('Only owner/finance can manage bank accounts.');

            return;
        }

        $validated = $this->validate([
            'bankAccountForm.account_name' => ['required', 'string', 'max:120'],
            'bankAccountForm.bank_name' => ['required', 'string', 'max:120'],
            'bankAccountForm.account_reference' => ['nullable', 'string', 'max:120'],
            'bankAccountForm.account_number_masked' => ['nullable', 'string', 'max:40'],
            'bankAccountForm.currency_code' => ['required', 'string', 'size:3'],
            'bankAccountForm.is_primary' => ['boolean'],
        ]);

        if ((bool) $validated['bankAccountForm']['is_primary']) {
            BankAccount::query()
                ->where('company_id', (int) $user->company_id)
                ->update(['is_primary' => false]);
        }

        $account = BankAccount::query()->create([
            'company_id' => (int) $user->company_id,
            'account_name' => (string) $validated['bankAccountForm']['account_name'],
            'bank_name' => (string) $validated['bankAccountForm']['bank_name'],
            'account_reference' => (string) ($validated['bankAccountForm']['account_reference'] ?? ''),
            'account_number_masked' => (string) ($validated['bankAccountForm']['account_number_masked'] ?? ''),
            'currency_code' => strtoupper((string) $validated['bankAccountForm']['currency_code']),
            'is_primary' => (bool) $validated['bankAccountForm']['is_primary'],
            'is_active' => true,
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        $this->selectedBankAccountId = (int) $account->id;
        $this->bankAccountForm = [
            'account_name' => '',
            'bank_name' => '',
            'account_reference' => '',
            'account_number_masked' => '',
            'currency_code' => 'NGN',
            'is_primary' => false,
        ];

        $tenantAuditLogger->log(
            companyId: (int) $user->company_id,
            action: 'tenant.treasury.bank_account.created',
            actor: $user,
            description: 'Bank account created from treasury daily reconciliation desk.',
            entityType: BankAccount::class,
            entityId: (int) $account->id,
            metadata: [
                'bank_name' => (string) $account->bank_name,
                'account_name' => (string) $account->account_name,
                'currency_code' => (string) $account->currency_code,
            ],
        );

        $this->setFeedback('Bank account created.');
    }

    public function importStatement(ImportBankStatementCsvService $importBankStatementCsvService): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperateDesk($user)) {
            $this->setFeedbackError('Only owner/finance can import statements.');

            return;
        }

        $validated = $this->validate([
            'selectedBankAccountId' => ['required', 'integer', 'min:1'],
            'statementFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = $importBankStatementCsvService->import(
            actor: $user,
            bankAccountId: (int) $validated['selectedBankAccountId'],
            csv: $this->statementFile,
        );

        $statement = $result['statement'];
        $this->selectedStatementId = (int) $statement->id;
        $this->statementFile = null;

        $this->setFeedback(sprintf(
            'Statement imported. Added %d line(s), skipped %d duplicate line(s).',
            (int) $result['imported'],
            (int) $result['skipped']
        ));
    }

    public function runAutoReconcile(AutoReconcileStatementService $autoReconcileStatementService): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperateDesk($user)) {
            $this->setFeedbackError('Only owner/finance can run auto-reconciliation.');

            return;
        }

        $statement = null;
        if ($this->selectedStatementId) {
            $statement = BankStatement::query()->find($this->selectedStatementId);
        }

        if (! $statement instanceof BankStatement) {
            $statement = BankStatement::query()
                ->when($this->selectedBankAccountId, fn (Builder $query) => $query->where('bank_account_id', (int) $this->selectedBankAccountId))
                ->latest('id')
                ->first();
        }

        if (! $statement instanceof BankStatement) {
            $this->setFeedbackError('No statement found to reconcile. Import a statement first.');

            return;
        }

        $summary = $autoReconcileStatementService->run($user, $statement);
        $this->selectedStatementId = (int) $statement->id;

        $this->setFeedback(sprintf(
            'Auto-reconcile complete. Matched %d line(s), opened %d exception(s), conflicts %d.',
            (int) $summary['matched'],
            (int) $summary['exceptions'],
            (int) $summary['conflicts']
        ));
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

        if (! $this->canOperateExceptions($user, $allowedRoles)) {
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

            // Ensure exception maker and checker are different when policy requires maker-checker.
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
            description: 'Treasury exception updated from daily reconciliation desk.',
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
        $user = auth()->user();
        $companyId = (int) ($user?->company_id ?? 0);

        $controls = $treasuryControlSettingsService->effectiveControls($companyId);
        $backlogAlertThreshold = max(1, (int) ($controls['reconciliation_backlog_alert_count_threshold'] ?? 25));
        $exceptionActionAllowedRoles = $this->normalizeRoles((array) ($controls['exception_action_allowed_roles'] ?? ['owner', 'finance']));
        $makerCheckerRequired = (bool) ($controls['exception_action_requires_maker_checker'] ?? true);

        $accounts = $this->readyToLoad
            ? BankAccount::query()
                ->where('company_id', $companyId)
                ->orderByDesc('is_primary')
                ->orderBy('bank_name')
                ->get()
            : collect();

        if (! $this->selectedBankAccountId && $accounts->isNotEmpty()) {
            $this->selectedBankAccountId = (int) $accounts->first()->id;
        }

        $statements = $this->readyToLoad
            ? BankStatement::query()
                ->with('account:id,bank_name,account_name,currency_code')
                ->where('company_id', $companyId)
                ->when($this->selectedBankAccountId, fn (Builder $query) => $query->where('bank_account_id', (int) $this->selectedBankAccountId))
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        $activeStatement = null;
        if ($this->selectedStatementId) {
            $activeStatement = $statements->firstWhere('id', (int) $this->selectedStatementId);
        }
        if (! $activeStatement && $statements->isNotEmpty()) {
            $activeStatement = $statements->first();
            $this->selectedStatementId = (int) $activeStatement->id;
        }

        $lineQuery = BankStatementLine::query()
            ->with('account:id,bank_name,account_name')
            ->where('company_id', $companyId)
            ->when($activeStatement instanceof BankStatement, fn (Builder $query) => $query->where('bank_statement_id', (int) $activeStatement->id))
            ->when($this->lineSearch, function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->where('line_reference', 'like', '%'.$this->lineSearch.'%')
                        ->orWhere('description', 'like', '%'.$this->lineSearch.'%');
                });
            })
            ->latest('posted_at')
            ->latest('id');

        $lines = $this->readyToLoad
            ? (clone $lineQuery)->paginate($this->linePerPage)
            : BankStatementLine::query()->whereRaw('1=0')->paginate($this->linePerPage);

        $unmatchedLinesQuery = BankStatementLine::query()
            ->with('account:id,bank_name,account_name')
            ->withCount(['exceptions as open_exception_count' => function (Builder $query): void {
                $query->where('exception_status', ReconciliationException::STATUS_OPEN);
            }])
            ->where('company_id', $companyId)
            ->where('is_reconciled', false)
            ->when($activeStatement instanceof BankStatement, fn (Builder $query) => $query->where('bank_statement_id', (int) $activeStatement->id))
            ->latest('posted_at')
            ->latest('id');

        $unmatchedLinesPreview = $this->readyToLoad
            ? (clone $unmatchedLinesQuery)->limit(8)->get()
            : collect();

        $openExceptionsQuery = ReconciliationException::query()
            ->with('line:id,bank_statement_id,line_reference,description,amount,currency_code,posted_at')
            ->where('company_id', $companyId)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->when($activeStatement instanceof BankStatement, function (Builder $query) use ($activeStatement): void {
                $query->whereHas('line', fn (Builder $lineQuery) => $lineQuery->where('bank_statement_id', (int) $activeStatement->id));
            });

        $openExceptionsPreview = $this->readyToLoad
            ? (clone $openExceptionsQuery)
                ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
                ->oldest('created_at')
                ->limit(8)
                ->get()
            : collect();

        $paymentRunsBase = PaymentRun::query()->where('company_id', $companyId);
        $paymentRunSummary = $this->readyToLoad
            ? [
                'processing' => (clone $paymentRunsBase)->where('run_status', PaymentRun::STATUS_PROCESSING)->count(),
                'failed_today' => (clone $paymentRunsBase)
                    ->where('run_status', PaymentRun::STATUS_FAILED)
                    ->whereDate('updated_at', today())
                    ->count(),
                'last_run_at' => optional((clone $paymentRunsBase)->latest('updated_at')->value('updated_at'))->format('M d, Y H:i'),
            ]
            : [
                'processing' => 0,
                'failed_today' => 0,
                'last_run_at' => null,
            ];

        $summary = $this->readyToLoad
            ? [
                'lines' => (clone $lineQuery)->count(),
                'reconciled' => (clone $lineQuery)->where('is_reconciled', true)->count(),
                'unreconciled' => (clone $lineQuery)->where('is_reconciled', false)->count(),
                'unreconciled_value' => (int) (clone $lineQuery)->where('is_reconciled', false)->sum('amount'),
                'open_exceptions' => (clone $openExceptionsQuery)->count(),
                'processing_runs' => (int) ($paymentRunSummary['processing'] ?? 0),
            ]
            : [
                'lines' => 0,
                'reconciled' => 0,
                'unreconciled' => 0,
                'unreconciled_value' => 0,
                'open_exceptions' => 0,
                'processing_runs' => 0,
            ];

        $latestAutoRunQuery = TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->where('action', 'tenant.treasury.reconciliation.auto_run')
            ->where('event_at', '>=', now()->startOfDay());

        if ($activeStatement instanceof BankStatement) {
            $latestAutoRunQuery->where('metadata->statement_reference', (string) $activeStatement->statement_reference);
        }

        $latestAutoRun = $this->readyToLoad ? $latestAutoRunQuery->latest('event_at')->first() : null;

        $importStatus = [
            'statement_reference' => (string) ($activeStatement?->statement_reference ?? '-'),
            'import_status' => (string) ($activeStatement?->import_status ?? 'not imported'),
            'imported_at' => optional($activeStatement?->imported_at)->format('M d, Y H:i'),
            'rows_imported' => (int) data_get((array) ($activeStatement?->metadata ?? []), 'rows_imported', 0),
            'rows_skipped' => (int) data_get((array) ($activeStatement?->metadata ?? []), 'rows_skipped', 0),
            'closing_balance' => (int) ($activeStatement?->closing_balance ?? 0),
        ];

        $closeDayChecklist = $this->buildCloseDayChecklist(
            activeStatement: $activeStatement,
            latestAutoRun: $latestAutoRun,
            openExceptionsCount: (int) ($summary['open_exceptions'] ?? 0),
            unreconciledCount: (int) ($summary['unreconciled'] ?? 0),
            processingRunsCount: (int) ($paymentRunSummary['processing'] ?? 0),
            backlogAlertThreshold: $backlogAlertThreshold,
        );

        return view('livewire.treasury.treasury-reconciliation-page', [
            'accounts' => $accounts,
            'statements' => $statements,
            'lines' => $lines,
            'unmatchedLinesPreview' => $unmatchedLinesPreview,
            'openExceptionsPreview' => $openExceptionsPreview,
            'summary' => $summary,
            'importStatus' => $importStatus,
            'paymentRunSummary' => $paymentRunSummary,
            'closeDayChecklist' => $closeDayChecklist,
            'backlogAlertThreshold' => $backlogAlertThreshold,
            'exceptionActionAllowedRoles' => $exceptionActionAllowedRoles,
            'makerCheckerRequired' => $makerCheckerRequired,
            'canOperate' => auth()->check() && $this->canOperateDesk(auth()->user()),
            'canResolveExceptions' => auth()->check() && $this->canOperateExceptions(auth()->user(), $exceptionActionAllowedRoles),
        ]);
    }

    /**
     * @return array<int, array{label:string,done:bool,note:string,action_label:?string,action_route:?string}>
     */
    private function buildCloseDayChecklist(
        ?BankStatement $activeStatement,
        ?TenantAuditEvent $latestAutoRun,
        int $openExceptionsCount,
        int $unreconciledCount,
        int $processingRunsCount,
        int $backlogAlertThreshold,
    ): array {
        $statementImportedToday = $activeStatement instanceof BankStatement
            && $activeStatement->imported_at
            && $activeStatement->imported_at->isToday();

        $autoRunCompletedToday = $latestAutoRun instanceof TenantAuditEvent;

        return [
            [
                'label' => 'Statement imported for today',
                'done' => $statementImportedToday,
                'note' => $statementImportedToday
                    ? 'Latest statement import is dated today.'
                    : 'Import today\'s statement before close-day signoff.',
                'action_label' => $statementImportedToday ? null : 'Import now',
                'action_route' => $statementImportedToday ? null : 'treasury.reconciliation',
            ],
            [
                'label' => 'Auto-reconcile run completed',
                'done' => $autoRunCompletedToday,
                'note' => $autoRunCompletedToday
                    ? 'Auto-reconcile was run for the active statement today.'
                    : 'Run auto-reconcile to classify matched/unmatched lines.',
                'action_label' => $autoRunCompletedToday ? null : 'Run auto-reconcile',
                'action_route' => $autoRunCompletedToday ? null : 'treasury.reconciliation',
            ],
            [
                'label' => 'Exception queue cleared or triaged',
                'done' => $openExceptionsCount === 0,
                'note' => $openExceptionsCount === 0
                    ? 'No open reconciliation exceptions.'
                    : sprintf('%d open exception(s) require resolve/waive decisions.', $openExceptionsCount),
                'action_label' => $openExceptionsCount === 0 ? null : 'Open exception queue',
                'action_route' => $openExceptionsCount === 0 ? null : 'treasury.reconciliation-exceptions',
            ],
            [
                'label' => 'Unreconciled backlog within threshold',
                'done' => $unreconciledCount <= $backlogAlertThreshold,
                'note' => $unreconciledCount <= $backlogAlertThreshold
                    ? sprintf('Unreconciled lines (%d) are within configured threshold (%d).', $unreconciledCount, $backlogAlertThreshold)
                    : sprintf('Unreconciled lines (%d) exceed configured threshold (%d).', $unreconciledCount, $backlogAlertThreshold),
                'action_label' => $unreconciledCount <= $backlogAlertThreshold ? null : 'Inspect unmatched lines',
                'action_route' => $unreconciledCount <= $backlogAlertThreshold ? null : 'treasury.reconciliation',
            ],
            [
                'label' => 'No payment runs stuck in processing',
                'done' => $processingRunsCount === 0,
                'note' => $processingRunsCount === 0
                    ? 'No processing payment runs waiting in queue.'
                    : sprintf('%d payment run(s) are still processing.', $processingRunsCount),
                'action_label' => $processingRunsCount === 0 ? null : 'Open payment runs',
                'action_route' => $processingRunsCount === 0 ? null : 'treasury.payment-runs',
            ],
        ];
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

    private function canOperateDesk(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }

    /**
     * @param  array<int, string>  $allowedRoles
     */
    private function canOperateExceptions(User $user, array $allowedRoles): bool
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
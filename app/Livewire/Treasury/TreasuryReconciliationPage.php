<?php

namespace App\Livewire\Treasury;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\MonoConnectAccount;
use App\Domains\Treasury\Models\PaymentRun;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Models\User;
use App\Services\AI\AiFeatureGateService;
use App\Services\AI\TreasuryReconciliationFlowAgentService;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\AutoReconcileStatementService;
use App\Services\Treasury\ImportBankStatementCsvService;
use App\Services\Treasury\ImportMonoStatementService;
use App\Services\Treasury\TreasuryControlSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Daily Bank Reconciliation')]
class TreasuryReconciliationPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const ALLOWED_LINE_PER_PAGE = [10, 25, 50];

    private const MAX_LINE_SEARCH_LENGTH = 120;

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

    public int $monoSyncDays = 7;

    public bool $flowAgentsEnabled = false;

    public bool $flowAgentsAdvisoryOnly = true;

    /**
     * @var array<int, array{
     *   risk_level:string,
     *   risk_score:int,
     *   confidence:int,
     *   suggested_match:string,
     *   suggested_match_type:string,
     *   why_blocked:string,
     *   next_action:string,
     *   summary:string,
     *   signals:array<int,string>,
     *   engine:string,
     *   generated_at:string
     * }>
     */
    public array $flowAgentInsights = [];

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

        $this->flowAgentsEnabled = app(AiFeatureGateService::class)->enabledForCompany((int) $user->company_id);
        $this->flowAgentsAdvisoryOnly = (bool) config('ai.guards.advisory_only', true);

        $this->normalizeWorkspaceFilters();
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedLineSearch(): void
    {
        $this->lineSearch = $this->normalizeSearch($this->lineSearch);
        $this->resetPage();
    }

    public function updatedLinePerPage(): void
    {
        $this->linePerPage = $this->normalizeLinePerPage($this->linePerPage);

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

    public function analyzeOpenExceptionWithFlowAgent(int $exceptionId): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        if (! $this->flowAgentsEnabled) {
            $this->setFeedbackError('Flow Agent is not enabled for this tenant.');

            return;
        }

        $exception = ReconciliationException::query()
            ->with('line:id,company_id,line_reference,description,amount,currency_code,posted_at,value_date')
            ->where('company_id', (int) $user->company_id)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->whereKey($exceptionId)
            ->first();

        if (! $exception) {
            $this->setFeedbackError('Selected exception is no longer available in your tenant scope.');

            return;
        }

        $insight = app(TreasuryReconciliationFlowAgentService::class)->analyze($exception);
        $this->flowAgentInsights[(int) $exception->id] = $insight;

        app(TenantAuditLogger::class)->log(
            companyId: (int) $user->company_id,
            action: 'tenant.treasury.reconciliation.exception.flow_agent_analyzed',
            actor: $user,
            description: 'Flow Agent analyzed treasury reconciliation exception from daily desk.',
            entityType: ReconciliationException::class,
            entityId: (int) $exception->id,
            metadata: [
                'exception_code' => (string) $exception->exception_code,
                'risk_level' => (string) ($insight['risk_level'] ?? 'low'),
                'risk_score' => (int) ($insight['risk_score'] ?? 0),
                'suggested_match_type' => (string) ($insight['suggested_match_type'] ?? 'none'),
                'engine' => (string) ($insight['engine'] ?? 'deterministic_treasury_reconciliation_rules'),
            ],
        );

        $this->setFeedback(
            'Flow Agent analyzed '.strtoupper((string) $exception->exception_code)
            .': '.ucfirst((string) ($insight['risk_level'] ?? 'low'))
            .' risk.'
        );
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
            'bankAccountForm.currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'bankAccountForm.is_primary' => ['boolean'],
        ]);

        $form = (array) ($validated['bankAccountForm'] ?? []);

        if ((bool) $validated['bankAccountForm']['is_primary']) {
            BankAccount::query()
                ->where('company_id', (int) $user->company_id)
                ->update(['is_primary' => false]);
        }

        $account = BankAccount::query()->create([
            'company_id' => (int) $user->company_id,
            'account_name' => trim((string) ($form['account_name'] ?? '')),
            'bank_name' => trim((string) ($form['bank_name'] ?? '')),
            'account_reference' => trim((string) ($form['account_reference'] ?? '')),
            'account_number_masked' => trim((string) ($form['account_number_masked'] ?? '')),
            'currency_code' => strtoupper(trim((string) ($form['currency_code'] ?? 'NGN'))),
            'is_primary' => (bool) ($form['is_primary'] ?? false),
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
            'selectedBankAccountId' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('bank_accounts', 'id')->where(
                    fn ($query) => $query->where('company_id', (int) $user->company_id)->where('is_active', true)
                ),
            ],
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

    public function syncMonoStatement(ImportMonoStatementService $importMonoStatementService): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperateDesk($user)) {
            $this->setFeedbackError('Only owner/finance can sync Mono statements.');

            return;
        }

        if (! $this->selectedBankAccountId) {
            $this->setFeedbackError('Select a bank account before syncing via Mono.');

            return;
        }

        $days = max(1, min(90, (int) $this->monoSyncDays));

        $result = $importMonoStatementService->sync(
            actor: $user,
            bankAccountId: (int) $this->selectedBankAccountId,
            from: Carbon::now()->subDays($days)->startOfDay(),
            to: Carbon::now()->endOfDay(),
        );

        $this->selectedStatementId = (int) $result['statement']->id;

        $this->setFeedback(sprintf(
            'Mono sync complete. Imported %d line(s), skipped %d duplicate(s).',
            (int) $result['imported'],
            (int) $result['skipped'],
        ));
    }

    public function runAutoReconcile(AutoReconcileStatementService $autoReconcileStatementService): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperateDesk($user)) {
            $this->setFeedbackError('Only owner/finance can run auto-reconciliation.');

            return;
        }

        if (! $this->selectedBankAccountId) {
            $this->setFeedbackError('Select a bank account before running auto-reconcile.');

            return;
        }

        $statement = null;
        if ($this->selectedStatementId) {
            $statement = BankStatement::query()
                ->where('company_id', (int) $user->company_id)
                ->where('bank_account_id', (int) $this->selectedBankAccountId)
                ->find((int) $this->selectedStatementId);
        }

        if (! $statement instanceof BankStatement) {
            $statement = BankStatement::query()
                ->where('company_id', (int) $user->company_id)
                ->where('bank_account_id', (int) $this->selectedBankAccountId)
                ->latest('id')
                ->first();
        }

        if (! $statement instanceof BankStatement) {
            $this->setFeedbackError('No statement found for this account. Import a statement first.');

            return;
        }

        $summary = $autoReconcileStatementService->run($user, $statement);
        $this->selectedStatementId = (int) $statement->id;

        $matched = (int) $summary['matched'];
        $exceptions = (int) $summary['exceptions'];
        $conflicts = (int) $summary['conflicts'];

        if ($matched === 0 && $exceptions === 0 && $conflicts === 0) {
            $this->setFeedback('All lines are already matched — nothing new to process.');

            return;
        }

        $this->setFeedback(sprintf(
            'Auto-reconcile done. Matched %d line(s)%s%s.',
            $matched,
            $exceptions > 0 ? ", {$exceptions} item(s) need attention" : '',
            $conflicts > 0 ? ", {$conflicts} conflict(s) flagged" : ''
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

        if (! $this->canOperateExceptions($user)) {
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
            'resolutionAction' => ['required', Rule::in(['resolved', 'waived'])],
            'resolutionNotes' => ['required', 'string', 'max:2000'],
        ]);

        $exception = ReconciliationException::query()
            ->with('line')
            ->where('company_id', (int) $user->company_id)
            ->whereKey((int) $this->selectedExceptionId)
            ->firstOrFail();

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

        $newStatus = (string) $validated['resolutionAction'] === 'waived'
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
                'flow_agent_insight' => $this->flowAgentInsights[(int) $exception->id] ?? null,
            ],
        );

        $this->closeResolutionModal();
        $this->setFeedback('Treasury exception updated.');
    }

    public function render(TreasuryControlSettingsService $treasuryControlSettingsService): View
    {
        $this->normalizeWorkspaceFilters();

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

        $activeMonoAccount = ($this->readyToLoad && $this->selectedBankAccountId)
            ? MonoConnectAccount::query()
                ->where('company_id', $companyId)
                ->where('bank_account_id', (int) $this->selectedBankAccountId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first()
            : null;

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
            'flowAgentsEnabled' => $this->flowAgentsEnabled,
            'flowAgentsAdvisoryOnly' => $this->flowAgentsAdvisoryOnly,
            'activeMonoAccount' => $activeMonoAccount,
            'canOperate' => auth()->check() && $this->canOperateDesk(auth()->user()),
            'canResolveExceptions' => auth()->check() && $this->canOperateExceptions(auth()->user()),
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
        return Gate::forUser($user)->allows('viewAny', BankStatement::class);
    }

    private function canOperateDesk(User $user): bool
    {
        return Gate::forUser($user)->allows('operate', BankStatement::class);
    }

    private function canOperateExceptions(User $user): bool
    {
        return Gate::forUser($user)->allows('resolveAny', ReconciliationException::class);
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

    private function normalizeWorkspaceFilters(): void
    {
        $this->linePerPage = $this->normalizeLinePerPage($this->linePerPage);
        $this->lineSearch = $this->normalizeSearch($this->lineSearch);
        $this->selectedBankAccountId = $this->normalizeOptionalPositiveInt($this->selectedBankAccountId);
        $this->selectedStatementId = $this->normalizeOptionalPositiveInt($this->selectedStatementId);
    }

    private function normalizeLinePerPage(int $linePerPage): int
    {
        return in_array($linePerPage, self::ALLOWED_LINE_PER_PAGE, true)
            ? $linePerPage
            : self::ALLOWED_LINE_PER_PAGE[0];
    }

    private function normalizeSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $normalized = trim($search);

        return $normalized === ''
            ? null
            : mb_substr($normalized, 0, self::MAX_LINE_SEARCH_LENGTH);
    }

    private function normalizeOptionalPositiveInt(?int $value): ?int
    {
        return $value && $value > 0
            ? (int) $value
            : null;
    }
}

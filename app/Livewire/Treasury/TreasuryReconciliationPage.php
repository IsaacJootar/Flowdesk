<?php

namespace App\Livewire\Treasury;

use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\AutoReconcileStatementService;
use App\Services\Treasury\ImportBankStatementCsvService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Treasury Reconciliation')]
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

    public function createBankAccount(TenantAuditLogger $tenantAuditLogger): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->canOperate($user)) {
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
            description: 'Bank account created from treasury reconciliation page.',
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
        if (! $user instanceof User || ! $this->canOperate($user)) {
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
        if (! $user instanceof User || ! $this->canOperate($user)) {
            $this->setFeedbackError('Only owner/finance can run auto-reconciliation.');

            return;
        }

        $statement = null;
        if ($this->selectedStatementId) {
            $statement = BankStatement::query()->find($this->selectedStatementId);
        }

        if (! $statement instanceof BankStatement) {
            $statement = BankStatement::query()
                ->when($this->selectedBankAccountId, fn ($query) => $query->where('bank_account_id', (int) $this->selectedBankAccountId))
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

    public function render(): View
    {
        $accounts = $this->readyToLoad
            ? BankAccount::query()
                ->where('company_id', (int) auth()->user()->company_id)
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
                ->where('company_id', (int) auth()->user()->company_id)
                ->when($this->selectedBankAccountId, fn ($query) => $query->where('bank_account_id', (int) $this->selectedBankAccountId))
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        $lineQuery = BankStatementLine::query()
            ->with('account:id,bank_name,account_name')
            ->where('company_id', (int) auth()->user()->company_id)
            ->when($this->selectedStatementId, fn ($query) => $query->where('bank_statement_id', (int) $this->selectedStatementId))
            ->when($this->lineSearch, function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('line_reference', 'like', '%'.$this->lineSearch.'%')
                        ->orWhere('description', 'like', '%'.$this->lineSearch.'%');
                });
            })
            ->latest('posted_at')
            ->latest('id');

        $lines = $this->readyToLoad
            ? (clone $lineQuery)->paginate($this->linePerPage)
            : BankStatementLine::query()->whereRaw('1=0')->paginate($this->linePerPage);

        $summary = $this->readyToLoad
            ? [
                'lines' => (clone $lineQuery)->count(),
                'reconciled' => (clone $lineQuery)->where('is_reconciled', true)->count(),
                'unreconciled' => (clone $lineQuery)->where('is_reconciled', false)->count(),
            ]
            : ['lines' => 0, 'reconciled' => 0, 'unreconciled' => 0];

        return view('livewire.treasury.treasury-reconciliation-page', [
            'accounts' => $accounts,
            'statements' => $statements,
            'lines' => $lines,
            'summary' => $summary,
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
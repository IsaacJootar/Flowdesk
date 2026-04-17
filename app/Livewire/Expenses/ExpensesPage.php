<?php

namespace App\Livewire\Expenses;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Actions\Expenses\UploadExpenseAttachment;
use App\Actions\Expenses\VoidExpense;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\Vendor;
use App\Services\ActivityLogger;
use App\Services\AI\ExpenseReceiptIntelligenceService;
use App\Services\ExpenseDuplicateDetector;
use App\Services\ExpensePolicyResolver;
use App\Services\TenantModuleAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class ExpensesPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Keep first render fast; data is loaded after the page is hydrated.
    public bool $readyToLoad = false;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $vendorFilter = 'all';

    public string $departmentFilter = 'all';

    public string $paymentMethodFilter = 'all';

    public string $statusFilter = 'all';

    public int $perPage = 10;

    public bool $showFormModal = false;

    public bool $showViewModal = false;

    public bool $showVoidModal = false;

    public bool $isEditing = false;

    public ?int $editingExpenseId = null;

    public ?int $selectedExpenseId = null;

    public ?int $voidingExpenseId = null;

    /** @var array<string, mixed>|null */
    public ?array $viewExpense = null;

    public string $vendorPickerSearch = '';

    public string $voidReason = '';

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var array<string, mixed> */
    public array $form = [
        'department_id' => '',
        'vendor_id' => '',
        'title' => '',
        'description' => '',
        'amount' => '',
        'expense_date' => '',
        'payment_method' => '',
        'paid_by_user_id' => '',
    ];

    /** @var array<int, mixed> */
    public array $newAttachments = [];

    public bool $duplicateOverride = false;

    public string $duplicateRisk = 'none';

    /** @var array<int, array{id:int,expense_code:string,title:string,amount:int,expense_date:string|null}> */
    public array $duplicateMatches = [];

    public ?string $duplicateWarning = null;

    public bool $showReceiptAgentPanel = false;

    public string $receiptAgentSummary = '';

    public ?string $receiptAgentGeneratedAt = null;

    public int $receiptAgentConfidence = 0;

    public ?string $receiptOcrNotice = null;

    public ?string $receiptSuggestedCategory = null;

    public ?string $receiptSuggestedReference = null;

    /** @var array{vendor_id:?int,expense_date:?string,amount:?int,title:?string} */
    public array $receiptSuggestionFields = [
        'vendor_id' => null,
        'expense_date' => null,
        'amount' => null,
        'title' => null,
    ];

    /** @var array<int, array{source:string,message:string}> */
    public array $receiptAgentSignals = [];

    public function mount(): void
    {
        $this->feedbackMessage = session('status');
         $this->form['expense_date'] = $this->companyToday();
        $this->form['paid_by_user_id'] = \Illuminate\Support\Facades\Auth::id();

        // Supports deep-link navigation from reports/modules into a pre-filtered expense list.
        $searchFromQuery = trim((string) request()->query('search', ''));
        if ($searchFromQuery !== '') {
            $this->search = $searchFromQuery;
        }
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->dateFrom = $this->normalizeDateInput($this->dateFrom);
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->dateTo = $this->normalizeDateInput($this->dateTo);
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedVendorFilter(): void
    {
        $this->vendorFilter = $this->normalizeEntityFilter($this->vendorFilter);
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->departmentFilter = $this->normalizeEntityFilter($this->departmentFilter);
        $this->resetPage();
    }

    public function updatedPaymentMethodFilter(): void
    {
        $this->paymentMethodFilter = $this->normalizePaymentMethodFilter($this->paymentMethodFilter);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);

        $this->resetPage();
    }

    /**
     * @throws AuthorizationException
     */
    public function openCreateModal(): void
    {
        if (! $this->canManage) {
            $this->setFeedbackError($this->createExpenseUnavailableReason ?? 'You are not allowed to post direct expenses.');

            return;
        }

        Gate::authorize('create', Expense::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showViewModal = false;
        $this->showVoidModal = false;
        $this->voidingExpenseId = null;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingExpenseId = null;
        $this->resetReceiptAgentState();
        $this->resetDuplicatePreview();
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function openEditModal(int $expenseId): void
    {
        $expense = $this->findExpenseOrFail($expenseId);
        Gate::authorize('update', $expense);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showViewModal = false;
        $this->showVoidModal = false;
        $this->voidingExpenseId = null;
        $this->isEditing = true;
        $this->editingExpenseId = $expense->id;
        $this->fillFormFromExpense($expense);
        $this->resetReceiptAgentState();
        $this->resetDuplicatePreview();
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function openViewModal(int $expenseId): void
    {
        $expense = $this->loadExpenseForView($expenseId);
        Gate::authorize('view', $expense);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showFormModal = false;
        $this->showVoidModal = false;
        $this->voidingExpenseId = null;
        $this->isEditing = false;
        $this->editingExpenseId = null;
        $this->selectedExpenseId = $expense->id;
        $this->voidReason = '';
        $this->showViewModal = true;
        $this->fillViewExpenseData($expense);
    }

    /**
     * @throws AuthorizationException
     */
    public function openVoidModal(int $expenseId): void
    {
        $expense = $this->findExpenseOrFail($expenseId);
        Gate::authorize('void', $expense);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showFormModal = false;
        $this->showViewModal = false;
        $this->isEditing = false;
        $this->editingExpenseId = null;
        $this->voidingExpenseId = $expense->id;
        $this->voidReason = '';
        $this->showVoidModal = true;
    }

    public function closeVoidModal(): void
    {
        $this->showVoidModal = false;
        $this->voidingExpenseId = null;
        $this->voidReason = '';
        $this->resetValidation();
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->viewExpense = null;
        $this->selectedExpenseId = null;
        $this->voidReason = '';
        $this->resetValidation();
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingExpenseId = null;
        $this->resetReceiptAgentState();
        $this->resetDuplicatePreview();
        $this->resetValidation();
    }

    public function analyzeReceiptAttachments(
        ExpenseReceiptIntelligenceService $expenseReceiptIntelligenceService,
        ActivityLogger $activityLogger
    ): void {
        $this->feedbackError = null;
        $user = \Illuminate\Support\Facades\Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        if ($companyId <= 0) {
            $this->setFeedbackError('You must belong to an organization before running Receipt Agent.');

            return;
        }

        if (empty($this->newAttachments)) {
            $this->setFeedbackError('Upload at least one receipt file before running Receipt Agent.');

            return;
        }

        $this->validate([
            'newAttachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ], [
            'newAttachments.*.max' => 'Each attachment must be 10MB or less.',
            'newAttachments.*.mimes' => 'Only PDF and image files are supported.',
        ]);

        $environment = $expenseReceiptIntelligenceService->environmentStatus();
        $hasImage = false;
        $hasPdf = false;
        foreach ($this->newAttachments as $attachment) {
            if (! $attachment || ! method_exists($attachment, 'getMimeType')) {
                continue;
            }

            $mime = strtolower((string) ($attachment->getMimeType() ?: ''));
            if (str_starts_with($mime, 'image/')) {
                $hasImage = true;
            }
            if ($mime === 'application/pdf') {
                $hasPdf = true;
            }
        }

        $notices = [];
        if ($hasImage && ! ((bool) ($environment['image_ocr_available'] ?? false))) {
            $notices[] = 'Image OCR is unavailable on this server (missing tesseract).';
        }
        if ($hasPdf && ! ((bool) ($environment['pdf_text_available'] ?? false))) {
            $notices[] = 'PDF text extraction is unavailable on this server (missing pdftotext).';
        }
        $this->receiptOcrNotice = $notices !== []
            ? implode(' ', $notices).' Receipt Agent will rely on filename hints.'
            : null;

        $vendors = Vendor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Vendor $vendor): array => [
                'id' => (int) $vendor->id,
                'name' => (string) $vendor->name,
            ])
            ->all();

        $result = $expenseReceiptIntelligenceService->analyzeBatch($this->newAttachments, $vendors);
        $this->showReceiptAgentPanel = true;
        $this->receiptAgentSummary = (string) ($result['summary'] ?? '');
        $this->receiptAgentGeneratedAt = now()->format('M d, Y H:i');
        $this->receiptAgentConfidence = (int) ($result['confidence'] ?? 0);
        $fields = (array) ($result['fields'] ?? []);
        $this->receiptSuggestionFields = [
            'vendor_id' => isset($fields['vendor_id']) ? (int) $fields['vendor_id'] : null,
            'expense_date' => isset($fields['expense_date']) ? (string) $fields['expense_date'] : null,
            'amount' => isset($fields['amount']) ? (int) $fields['amount'] : null,
            'title' => isset($fields['title']) ? (string) $fields['title'] : null,
        ];
        $this->receiptSuggestedReference = isset($fields['reference']) ? (string) $fields['reference'] : null;
        $this->receiptSuggestedCategory = isset($fields['category']) ? (string) $fields['category'] : null;
        $this->receiptAgentSignals = array_values(array_filter(
            (array) ($result['signals'] ?? []),
            fn ($signal): bool => is_array($signal)
        ));

        $activityLogger->log(
            action: 'expense.receipt.analysis.generated',
            entityType: Expense::class,
            entityId: $this->editingExpenseId,
            metadata: [
                'is_editing' => $this->isEditing,
                'confidence' => $this->receiptAgentConfidence,
                'suggested_fields' => $this->receiptSuggestionFields,
                'suggested_reference' => $this->receiptSuggestedReference,
                'suggested_category' => $this->receiptSuggestedCategory,
                'files_count' => count($this->newAttachments),
                'environment' => $environment,
                'ocr_notice' => $this->receiptOcrNotice,
                'engine' => (string) ($result['engine'] ?? 'deterministic'),
                'ai_model' => (string) (($result['ai_model'] ?? '') ?: ''),
                'fallback_used' => (bool) ($result['fallback_used'] ?? false),
            ],
            companyId: $companyId,
            userId: (int) ($user?->id ?? 0),
        );
    }

    public function applyReceiptSuggestions(ActivityLogger $activityLogger): void
    {
        if (! $this->showReceiptAgentPanel) {
            return;
        }

        if (($this->receiptSuggestionFields['vendor_id'] ?? null) !== null) {
            $this->form['vendor_id'] = (string) ((int) $this->receiptSuggestionFields['vendor_id']);
        }
        if (($this->receiptSuggestionFields['expense_date'] ?? null) !== null) {
            $this->form['expense_date'] = (string) $this->receiptSuggestionFields['expense_date'];
        }
        if (($this->receiptSuggestionFields['amount'] ?? null) !== null) {
            $this->form['amount'] = (string) ((int) $this->receiptSuggestionFields['amount']);
        }
        if (($this->receiptSuggestionFields['title'] ?? null) !== null) {
            $this->form['title'] = (string) $this->receiptSuggestionFields['title'];
        }

        $notes = [];
        if ($this->receiptSuggestedReference !== null && $this->receiptSuggestedReference !== '') {
            $notes[] = 'Receipt Ref: '.$this->receiptSuggestedReference;
        }
        if ($this->receiptSuggestedCategory !== null && $this->receiptSuggestedCategory !== '') {
            $notes[] = 'Category Hint: '.str_replace('_', ' ', $this->receiptSuggestedCategory);
        }
        if ($notes !== []) {
            $description = trim((string) ($this->form['description'] ?? ''));
            foreach ($notes as $note) {
                if (! str_contains(strtolower($description), strtolower($note))) {
                    $description .= ($description !== '' ? PHP_EOL : '').$note;
                }
            }
            $this->form['description'] = $description;
        }

        $this->duplicateOverride = false;
        $this->syncDuplicatePreview($this->analyzeDuplicateRisk($this->formPayload()));
        $this->setFeedback('Receipt suggestions applied. Review and post when ready.');

        $activityLogger->log(
            action: 'expense.receipt.suggestion.applied',
            entityType: Expense::class,
            entityId: $this->editingExpenseId,
            metadata: [
                'is_editing' => $this->isEditing,
                'applied_fields' => $this->receiptSuggestionFields,
                'applied_reference' => $this->receiptSuggestedReference,
                'applied_category' => $this->receiptSuggestedCategory,
            ],
            companyId: (int) (\Illuminate\Support\Facades\Auth::user()?->company_id ?? 0),
            userId: (int) (\Illuminate\Support\Facades\Auth::id() ?? 0),
        );
    }

    public function dismissReceiptSuggestions(ActivityLogger $activityLogger): void
    {
        if (! $this->showReceiptAgentPanel) {
            return;
        }

        $activityLogger->log(
            action: 'expense.receipt.suggestion.dismissed',
            entityType: Expense::class,
            entityId: $this->editingExpenseId,
            metadata: [
                'is_editing' => $this->isEditing,
                'suggested_fields' => $this->receiptSuggestionFields,
            ],
            companyId: (int) (\Illuminate\Support\Facades\Auth::user()?->company_id ?? 0),
            userId: (int) (\Illuminate\Support\Facades\Auth::id() ?? 0),
        );

        $this->resetReceiptAgentState();
    }

    public function save(
        CreateExpense $createExpense,
        UpdateExpense $updateExpense,
        UploadExpenseAttachment $uploadExpenseAttachment
    ): void {
        $this->feedbackError = null;
        $this->validate($this->formRules(), $this->formMessages());
        $payload = $this->formPayload();
        $duplicateAnalysis = $this->analyzeDuplicateRisk($payload);
        $this->syncDuplicatePreview($duplicateAnalysis);

        if ($this->duplicateRisk === 'hard') {
            $this->setFeedbackError('Exact duplicate detected (same date, vendor, amount, and title). Posting is blocked.');

            return;
        }

        if ($this->duplicateRisk === 'soft' && ! $this->duplicateOverride) {
            $this->setFeedbackError('Possible duplicate found. Review matches and tick override to continue.');

            return;
        }

        try {
            if ($this->isEditing && $this->editingExpenseId) {
                // Edit path keeps the current expense id and applies no-change/duplicate guardrails in action.
                $expense = $this->findExpenseOrFail($this->editingExpenseId);
                $updatedExpense = $updateExpense(\Illuminate\Support\Facades\Auth::user(), $expense, $payload);
                $this->setFeedback('Expense updated successfully.');
                $this->attachUploadedFiles($uploadExpenseAttachment, $updatedExpense);
            } else {
                // Create path generates a new expense code and posts immediately.
                $expense = $createExpense(\Illuminate\Support\Facades\Auth::user(), $payload);
                $this->setFeedback('Expense recorded successfully.');
                $this->attachUploadedFiles($uploadExpenseAttachment, $expense);
            }
        } catch (ValidationException $exception) {
            if (array_key_exists('authorization', $exception->errors())) {
                $this->setFeedbackError((string) ($exception->errors()['authorization'][0] ?? 'You are not allowed to save this expense.'));

                return;
            }

            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to save expense. Please try again.');
            return;
        }

        $this->closeFormModal();
        $this->resetPage();
    }

    public function submitVoidExpense(VoidExpense $voidExpense): void
    {
        $this->feedbackError = null;

        if (! $this->voidingExpenseId) {
            return;
        }

        $expense = $this->findExpenseOrFail($this->voidingExpenseId);

        try {
            $voidExpense->__invoke(\Illuminate\Support\Facades\Auth::user(), $expense, ['reason' => $this->voidReason]);
        } catch (ValidationException $exception) {
            if (array_key_exists('authorization', $exception->errors())) {
                $this->setFeedbackError((string) ($exception->errors()['authorization'][0] ?? 'You are not allowed to void this expense.'));

                return;
            }

            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to void this expense now.');
            return;
        }

        $this->setFeedback('Expense voided successfully.');
        $this->closeVoidModal();

        if ($this->showViewModal && $this->selectedExpenseId === $expense->id) {
            $this->refreshSelectedExpenseView();
        }

        $this->resetPage();
    }

    public function getCanManageProperty(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            return false;
        }

        if (! app(TenantModuleAccessService::class)->moduleEnabled($user, 'expenses')) {
            return false;
        }

        return (bool) app(ExpensePolicyResolver::class)->canCreateDirect($user)['allowed'];
    }

    public function getCreateExpenseUnavailableReasonProperty(): ?string
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            return 'You must be signed in to post expenses.';
        }

        if (! app(TenantModuleAccessService::class)->moduleEnabled($user, 'expenses')) {
            return 'Expenses module is disabled for this organization plan.';
        }

        $decision = app(ExpensePolicyResolver::class)->canCreateDirect($user);
        if (! ((bool) ($decision['allowed'] ?? false))) {
            return (string) ($decision['reason'] ?? 'You are not allowed to post direct expenses.');
        }

        return null;
    }

    public function getCanEditSelectedExpenseProperty(): bool
    {
        if (! $this->selectedExpenseId) {
            return false;
        }

        $expense = Expense::query()->find($this->selectedExpenseId);
        if (! $expense) {
            return false;
        }

        return Gate::allows('update', $expense);
    }

    public function render(): View
    {
        $this->normalizeFilterState();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $vendors = Vendor::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $vendorPickerOptions = Vendor::query()
            ->when($this->vendorPickerSearch !== '', fn ($query) => $query->where('name', 'like', '%'.$this->vendorPickerSearch.'%'))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name']);

        $users = \Illuminate\Support\Facades\Auth::user()
            ? \Illuminate\Support\Facades\Auth::user()->company->users()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $expenses = $this->readyToLoad
            ? $this->expenseQuery()->paginate($this->perPage)
            : Expense::query()->whereRaw('1 = 0')->paginate($this->perPage);

        return view('livewire.expenses.expenses-page', [
            'expenses' => $expenses,
            'departments' => $departments,
            'vendors' => $vendors,
            'vendorPickerOptions' => $vendorPickerOptions,
            'users' => $users,
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    private function expenseQuery()
    {
        // Query powers table + summary cards; keep all filters in one place for consistency.
        return Expense::query()
            ->with(['department:id,name', 'vendor:id,name', 'creator:id,name', 'request:id,request_code,title'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('expense_code', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('request', fn ($requestQuery) => $requestQuery->where('request_code', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('expense_date', '<=', $this->dateTo))
            ->when($this->vendorFilter !== 'all', fn ($query) => $query->where('vendor_id', $this->vendorFilter))
            ->when($this->departmentFilter !== 'all', fn ($query) => $query->where('department_id', $this->departmentFilter))
            ->when($this->paymentMethodFilter !== 'all', fn ($query) => $query->where('payment_method', $this->paymentMethodFilter))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('expense_date')
            ->latest('id');
    }

    private function findExpenseOrFail(int $expenseId): Expense
    {
        /** @var Expense $expense */
        $expense = Expense::query()->findOrFail($expenseId);

        return $expense;
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        return [
            'department_id' => (int) $this->form['department_id'],
            'vendor_id' => $this->form['vendor_id'] !== '' ? (int) $this->form['vendor_id'] : null,
            'title' => trim((string) $this->form['title']),
            'description' => $this->nullableString($this->form['description']),
            'amount' => (int) $this->form['amount'],
            'expense_date' => (string) $this->form['expense_date'],
            'payment_method' => $this->nullableString($this->form['payment_method']),
            'paid_by_user_id' => $this->form['paid_by_user_id'] !== '' ? (int) $this->form['paid_by_user_id'] : null,
            'duplicate_override' => $this->duplicateOverride,
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'department_id' => '',
            'vendor_id' => '',
            'title' => '',
            'description' => '',
            'amount' => '',
            'expense_date' => $this->companyToday(),
            'payment_method' => '',
            'paid_by_user_id' => \Illuminate\Support\Facades\Auth::id() ?? '',
        ];
        $this->vendorPickerSearch = '';
        $this->newAttachments = [];
        $this->duplicateOverride = false;
        $this->resetDuplicatePreview();
        $this->resetReceiptAgentState();
    }

    private function fillFormFromExpense(Expense $expense): void
    {
        $this->form = [
            'department_id' => (string) $expense->department_id,
            'vendor_id' => $expense->vendor_id ? (string) $expense->vendor_id : '',
            'title' => (string) $expense->title,
            'description' => (string) ($expense->description ?? ''),
            'amount' => (string) $expense->amount,
            'expense_date' => $expense->expense_date?->format('Y-m-d') ?? $this->companyToday(),
            'payment_method' => (string) ($expense->payment_method ?? ''),
            'paid_by_user_id' => $expense->paid_by_user_id ? (string) $expense->paid_by_user_id : '',
        ];
        $this->vendorPickerSearch = '';
        $this->newAttachments = [];
        $this->duplicateOverride = false;
        $this->resetDuplicatePreview();
        $this->resetReceiptAgentState();
    }

    private function attachUploadedFiles(UploadExpenseAttachment $uploadExpenseAttachment, Expense $expense): void
    {
        if (empty($this->newAttachments)) {
            return;
        }

        foreach ($this->newAttachments as $file) {
            if ($file) {
                $uploadExpenseAttachment(\Illuminate\Support\Facades\Auth::user(), $expense, $file);
            }
        }

        $this->newAttachments = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function formRules(): array
    {
        $companyId = (int) (\Illuminate\Support\Facades\Auth::user()?->company_id ?? 0);

        return [
            'form.department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'form.vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('vendors', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'form.title' => ['required', 'string', 'max:180'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.amount' => ['required', 'integer', 'min:1'],
            'form.expense_date' => ['required', 'date'],
            'form.payment_method' => ['nullable', Rule::in($this->paymentMethods())],
            'form.paid_by_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)),
            ],
            'newAttachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function formMessages(): array
    {
        return [
            'form.department_id.required' => 'Department is required.',
            'form.title.required' => 'Expense title is required.',
            'form.amount.required' => 'Amount is required.',
            'form.amount.min' => 'Amount must be greater than zero.',
            'form.expense_date.required' => 'Expense date is required.',
            'form.payment_method.in' => 'Select a valid payment method.',
            'newAttachments.*.max' => 'Each attachment must be 10MB or less.',
            'newAttachments.*.mimes' => 'Only PDF and image files are supported.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function paymentMethods(): array
    {
        return ['cash', 'transfer', 'pos', 'online', 'cheque'];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }


    private function companyTimezone(): string
    {
        $timezone = trim((string) (\Illuminate\Support\Facades\Auth::user()?->company?->timezone ?? config('app.timezone', 'Africa/Lagos')));

        return $timezone !== '' ? $timezone : 'Africa/Lagos';
    }

    private function companyToday(): string
    {
        return Carbon::now($this->companyTimezone())->toDateString();
    }

    private function normalizeFilterState(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->paymentMethodFilter = $this->normalizePaymentMethodFilter($this->paymentMethodFilter);
        $this->vendorFilter = $this->normalizeEntityFilter($this->vendorFilter);
        $this->departmentFilter = $this->normalizeEntityFilter($this->departmentFilter);
        $this->dateFrom = $this->normalizeDateInput($this->dateFrom);
        $this->dateTo = $this->normalizeDateInput($this->dateTo);
        $this->normalizeDateRange();
        $this->perPage = $this->normalizePerPage($this->perPage);
    }

    private function normalizeStatusFilter(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['all', 'posted', 'void'], true) ? $normalized : 'all';
    }

    private function normalizePaymentMethodFilter(string $value): string
    {
        $normalized = strtolower(trim($value));

        return $normalized === 'all' || in_array($normalized, $this->paymentMethods(), true)
            ? $normalized
            : 'all';
    }

    private function normalizeEntityFilter(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strtolower($normalized) === 'all') {
            return 'all';
        }

        if (! ctype_digit($normalized)) {
            return 'all';
        }

        return ((int) $normalized) > 0 ? (string) ((int) $normalized) : 'all';
    }

    private function normalizeDateInput(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasWarnings = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if (! $parsed instanceof \DateTimeImmutable || $hasWarnings) {
            return '';
        }

        return $parsed->format('Y-m-d');
    }

    private function normalizeDateRange(): void
    {
        if ($this->dateFrom !== '' && $this->dateTo !== '' && $this->dateFrom > $this->dateTo) {
            $this->dateTo = '';
        }
    }

    private function normalizePerPage(int $value): int
    {
        return in_array($value, [10, 25, 50], true) ? $value : 10;
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

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        // Map action-level field keys back to Livewire-bound input names.
        $mapped = [];
        $formFields = [
            'department_id',
            'vendor_id',
            'title',
            'description',
            'amount',
            'expense_date',
            'payment_method',
            'paid_by_user_id',
        ];

        foreach ($errors as $key => $messages) {
            if ($key === 'reason') {
                $mapped['voidReason'] = $messages;
                continue;
            }

            if (str_starts_with($key, 'form.') || str_starts_with($key, 'newAttachments.')) {
                $mapped[$key] = $messages;
                continue;
            }

            if ($key === 'duplicate_override') {
                $mapped['duplicateOverride'] = $messages;
                continue;
            }

            if (in_array($key, $formFields, true)) {
                $mapped['form.'.$key] = $messages;
                continue;
            }

            $mapped[$key] = $messages;
        }

        return $mapped;
    }

    public function attachmentDownloadUrlById(int $attachmentId): string
    {
        return route('expenses.attachments.download', ['attachment' => $attachmentId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   risk:'none'|'soft'|'hard',
     *   matches:array<int, array{id:int,expense_code:string,title:string,amount:int,expense_date:string|null}>
     * }
     */
    private function analyzeDuplicateRisk(array $payload): array
    {
        $companyId = (int) (\Illuminate\Support\Facades\Auth::user()?->company_id ?? 0);
        if ($companyId <= 0) {
            return ['risk' => 'none', 'matches' => []];
        }

        return app(ExpenseDuplicateDetector::class)->analyze(
            companyId: $companyId,
            input: [
                'amount' => (int) ($payload['amount'] ?? 0),
                'expense_date' => (string) ($payload['expense_date'] ?? ''),
                'title' => (string) ($payload['title'] ?? ''),
                'vendor_id' => $payload['vendor_id'] ?? null,
            ],
            excludeExpenseId: $this->isEditing && $this->editingExpenseId ? (int) $this->editingExpenseId : null
        );
    }

    /**
     * @param  array{
     *   risk:'none'|'soft'|'hard',
     *   matches:array<int, array{id:int,expense_code:string,title:string,amount:int,expense_date:string|null}>
     * }  $analysis
     */
    private function syncDuplicatePreview(array $analysis): void
    {
        $risk = (string) ($analysis['risk'] ?? 'none');
        $matches = (array) ($analysis['matches'] ?? []);

        $this->duplicateRisk = in_array($risk, ['none', 'soft', 'hard'], true) ? $risk : 'none';
        $this->duplicateMatches = array_values(array_filter($matches, fn ($match): bool => is_array($match)));

        $this->duplicateWarning = match ($this->duplicateRisk) {
            'hard' => 'Exact duplicate detected. Save is blocked.',
            'soft' => 'Possible duplicate detected. Review matches and confirm override to continue.',
            default => null,
        };
    }

    private function resetDuplicatePreview(): void
    {
        $this->duplicateRisk = 'none';
        $this->duplicateMatches = [];
        $this->duplicateWarning = null;
    }

    private function resetReceiptAgentState(): void
    {
        $this->showReceiptAgentPanel = false;
        $this->receiptAgentSummary = '';
        $this->receiptAgentGeneratedAt = null;
        $this->receiptAgentConfidence = 0;
        $this->receiptOcrNotice = null;
        $this->receiptSuggestedCategory = null;
        $this->receiptSuggestedReference = null;
        $this->receiptSuggestionFields = [
            'vendor_id' => null,
            'expense_date' => null,
            'amount' => null,
            'title' => null,
        ];
        $this->receiptAgentSignals = [];
    }

    private function loadExpenseForView(int $expenseId): Expense
    {
        return Expense::query()
            ->with([
                'department:id,name',
                'vendor:id,name',
                'paidBy:id,name',
                'creator:id,name',
                'request:id,request_code,title',
                'attachments' => fn ($query) => $query->latest('uploaded_at'),
            ])
            ->findOrFail($expenseId);
    }

    private function refreshSelectedExpenseView(): void
    {
        if (! $this->selectedExpenseId) {
            return;
        }

        $this->fillViewExpenseData($this->loadExpenseForView($this->selectedExpenseId));
    }

    private function fillViewExpenseData(Expense $expense): void
    {
        // Normalize details for modal rendering so blade stays presentation-only.
        $this->viewExpense = [
            'id' => $expense->id,
            'expense_code' => $expense->expense_code,
            'source_label' => $expense->is_direct ? 'Direct' : 'From Request',
            'source_description' => $expense->is_direct
                ? 'Posted directly in Expenses'
                : ($expense->request?->request_code ? 'Linked to '.$expense->request->request_code : 'Linked request record'),
            'source_request_code' => $expense->request?->request_code,
            'source_request_title' => $expense->request?->title,
            'source_request_url' => $expense->request_id ? route('requests.index', ['open_request_id' => (int) $expense->request_id]) : null,
            'title' => $expense->title,
            'amount' => (int) $expense->amount,
            'expense_date' => $expense->expense_date?->format('M d, Y'),
            'status' => $expense->status,
            'department' => $expense->department?->name ?? '-',
            'vendor' => $expense->vendor?->name ?? 'Unlinked',
            'payment_method' => $expense->payment_method ? ucfirst($expense->payment_method) : 'Not specified',
            'created_by' => $expense->creator?->name ?? '-',
            'paid_by' => $expense->paidBy?->name ?? 'Unspecified',
            'description' => $expense->description ?: '-',
            'attachments' => $expense->attachments
                ->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => strtoupper($attachment->mime_type),
                    'file_size_kb' => number_format($attachment->file_size / 1024, 1),
                    'uploaded_at' => optional($attachment->uploaded_at)->format('M d, Y H:i'),
                ])
                ->all(),
        ];
    }
}


<?php

namespace App\Livewire\Expenses;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Actions\Expenses\UploadExpenseAttachment;
use App\Actions\Expenses\VoidExpense;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\Vendor;
use App\Services\ExpenseDuplicateDetector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
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

    public bool $readyToLoad = false;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $vendorFilter = 'all';

    public string $departmentFilter = 'all';

    public string $paymentMethodFilter = 'all';

    public string $statusFilter = 'all';

    public string $sourceFilter = 'all';

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

    /** @var array{risk: 'none'|'soft'|'hard', matches: array<int, array{id:int, expense_code:string, title:string, amount:int, expense_date:string|null}>}|null */
    public ?array $duplicateWarning = null;

    /** @var array<string, mixed> */
    public array $form = [
        'department_id' => '',
        'vendor_id' => '',
        'source_mode' => 'direct',
        'request_reference' => '',
        'title' => '',
        'description' => '',
        'amount' => '',
        'expense_date' => '',
        'payment_method' => '',
        'paid_by_user_id' => '',
        'duplicate_override' => false,
    ];

    /** @var array<int, mixed> */
    public array $newAttachments = [];

    public function mount(): void
    {
        $this->feedbackMessage = session('status');
        $this->form['expense_date'] = now()->toDateString();
        $this->form['paid_by_user_id'] = auth()->id();
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
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedVendorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSourceFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @throws AuthorizationException
     */
    public function openCreateModal(): void
    {
        Gate::authorize('create', Expense::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showViewModal = false;
        $this->showVoidModal = false;
        $this->voidingExpenseId = null;
        $this->duplicateWarning = null;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingExpenseId = null;
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
        $this->duplicateWarning = null;
        $this->isEditing = true;
        $this->editingExpenseId = $expense->id;
        $this->fillFormFromExpense($expense);
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
        $this->duplicateWarning = null;
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
        $this->duplicateWarning = null;
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
        $this->duplicateWarning = null;
        $this->resetValidation();
    }

    public function save(
        CreateExpense $createExpense,
        UpdateExpense $updateExpense,
        UploadExpenseAttachment $uploadExpenseAttachment,
        ExpenseDuplicateDetector $expenseDuplicateDetector
    ): void {
        $this->feedbackError = null;
        $this->validate($this->formRules(), $this->formMessages());
        $payload = $this->formPayload();

        try {
            $editingExpense = null;

            if ($this->isEditing && $this->editingExpenseId) {
                $editingExpense = $this->findExpenseOrFail($this->editingExpenseId);
            }

            if (! $this->guardDuplicateFlow($expenseDuplicateDetector, $payload, $editingExpense)) {
                return;
            }

            if ($editingExpense) {
                $updatedExpense = $updateExpense(auth()->user(), $editingExpense, $payload);
                $this->setFeedback('Expense updated successfully.');
                $this->attachUploadedFiles($uploadExpenseAttachment, $updatedExpense);
            } else {
                $expense = $createExpense(auth()->user(), $payload);
                $this->setFeedback('Expense recorded successfully.');
                $this->attachUploadedFiles($uploadExpenseAttachment, $expense);
            }
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (array_key_exists('no_changes', $errors)) {
                $this->feedbackError = null;
                $this->addError('form.no_changes', (string) ($errors['no_changes'][0] ?? 'No changes made.'));

                return;
            }

            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to save expense. Please try again.');
            return;
        }

        $this->closeFormModal();
        $this->resetPage();
    }

    public function updatedFormTitle(): void
    {
        $this->resetErrorBag('form.no_changes');
        $this->resetDuplicateWarningState();
    }

    public function updatedFormAmount(): void
    {
        $this->resetErrorBag('form.no_changes');
        $this->resetDuplicateWarningState();
    }

    public function updatedFormExpenseDate(): void
    {
        $this->resetErrorBag('form.no_changes');
        $this->resetDuplicateWarningState();
    }

    public function updatedFormVendorId(): void
    {
        $this->resetErrorBag('form.no_changes');
        $this->resetDuplicateWarningState();
    }

    public function updated(string $propertyName, mixed $value = null): void
    {
        if (str_starts_with($propertyName, 'form.')) {
            $this->resetErrorBag('form.no_changes');
        }
    }

    public function submitVoidExpense(VoidExpense $voidExpense): void
    {
        $this->feedbackError = null;

        if (! $this->voidingExpenseId) {
            return;
        }

        $expense = $this->findExpenseOrFail($this->voidingExpenseId);

        try {
            $voidExpense(auth()->user(), $expense, ['reason' => $this->voidReason]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable $exception) {
            report($exception);
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
        return Gate::allows('create', Expense::class);
    }

    public function render(): View
    {
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

        $users = auth()->user()
            ? auth()->user()->company->users()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $expenses = $this->readyToLoad
            ? $this->expenseQuery()->paginate(12)
            : Expense::query()->whereRaw('1 = 0')->paginate(12);

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
        return Expense::query()
            ->with(['department:id,name', 'vendor:id,name', 'creator:id,name'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('expense_code', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('expense_date', '<=', $this->dateTo))
            ->when($this->vendorFilter !== 'all', fn ($query) => $query->where('vendor_id', $this->vendorFilter))
            ->when($this->departmentFilter !== 'all', fn ($query) => $query->where('department_id', $this->departmentFilter))
            ->when($this->paymentMethodFilter !== 'all', fn ($query) => $query->where('payment_method', $this->paymentMethodFilter))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when(
                $this->sourceFilter !== 'all',
                fn ($query) => $query->where('is_direct', $this->sourceFilter === 'direct')
            )
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
        $isDirect = ($this->form['source_mode'] ?? 'direct') !== 'request';

        return [
            'department_id' => (int) $this->form['department_id'],
            'vendor_id' => $this->form['vendor_id'] !== '' ? (int) $this->form['vendor_id'] : null,
            'is_direct' => $isDirect,
            'request_id' => $isDirect ? null : (int) $this->form['request_reference'],
            'title' => trim((string) $this->form['title']),
            'description' => $this->nullableString($this->form['description']),
            'amount' => (int) $this->form['amount'],
            'expense_date' => (string) $this->form['expense_date'],
            'payment_method' => $this->nullableString($this->form['payment_method']),
            'paid_by_user_id' => $this->form['paid_by_user_id'] !== '' ? (int) $this->form['paid_by_user_id'] : null,
            'duplicate_override' => (bool) ($this->form['duplicate_override'] ?? false),
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'department_id' => '',
            'vendor_id' => '',
            'source_mode' => 'direct',
            'request_reference' => '',
            'title' => '',
            'description' => '',
            'amount' => '',
            'expense_date' => now()->toDateString(),
            'payment_method' => '',
            'paid_by_user_id' => auth()->id() ?? '',
            'duplicate_override' => false,
        ];
        $this->vendorPickerSearch = '';
        $this->newAttachments = [];
    }

    private function fillFormFromExpense(Expense $expense): void
    {
        $this->form = [
            'department_id' => (string) $expense->department_id,
            'vendor_id' => $expense->vendor_id ? (string) $expense->vendor_id : '',
            'source_mode' => $expense->is_direct ? 'direct' : 'request',
            'request_reference' => $expense->request_id ? (string) $expense->request_id : '',
            'title' => (string) $expense->title,
            'description' => (string) ($expense->description ?? ''),
            'amount' => (string) $expense->amount,
            'expense_date' => $expense->expense_date?->format('Y-m-d') ?? now()->toDateString(),
            'payment_method' => (string) ($expense->payment_method ?? ''),
            'paid_by_user_id' => $expense->paid_by_user_id ? (string) $expense->paid_by_user_id : '',
            'duplicate_override' => false,
        ];
        $this->vendorPickerSearch = '';
        $this->newAttachments = [];
    }

    private function attachUploadedFiles(UploadExpenseAttachment $uploadExpenseAttachment, Expense $expense): void
    {
        if (empty($this->newAttachments)) {
            return;
        }

        foreach ($this->newAttachments as $file) {
            if ($file) {
                $uploadExpenseAttachment(auth()->user(), $expense, $file);
            }
        }

        $this->newAttachments = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function formRules(): array
    {
        return [
            'form.department_id' => ['required', 'integer'],
            'form.vendor_id' => ['nullable', 'integer'],
            'form.source_mode' => ['required', Rule::in(['direct', 'request'])],
            'form.request_reference' => ['required_if:form.source_mode,request', 'nullable', 'integer', 'min:1'],
            'form.title' => ['required', 'string', 'max:180'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.amount' => ['required', 'integer', 'min:1'],
            'form.expense_date' => ['required', 'date'],
            'form.payment_method' => ['nullable', Rule::in($this->paymentMethods())],
            'form.paid_by_user_id' => ['nullable', 'integer'],
            'form.duplicate_override' => ['nullable', 'boolean'],
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
            'form.request_reference.required_if' => 'Request reference is required for request-linked expenses.',
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
        $mapped = [];
        $formFields = [
            'department_id',
            'vendor_id',
            'source_mode',
            'request_reference',
            'title',
            'description',
            'amount',
            'expense_date',
            'payment_method',
            'paid_by_user_id',
            'duplicate_override',
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

            if (in_array($key, $formFields, true)) {
                $mapped['form.'.$key] = $messages;
                continue;
            }

            $mapped[$key] = $messages;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function guardDuplicateFlow(
        ExpenseDuplicateDetector $expenseDuplicateDetector,
        array $payload,
        ?Expense $editingExpense = null
    ): bool {
        $companyId = auth()->user()?->company_id;

        if (! $companyId) {
            return true;
        }

        $duplicateAnalysis = $expenseDuplicateDetector->analyze(
            companyId: (int) $companyId,
            input: $payload,
            excludeExpenseId: $editingExpense?->id
        );

        if ($duplicateAnalysis['risk'] === 'none') {
            $this->duplicateWarning = null;
            $this->resetErrorBag('form.duplicate_override');

            return true;
        }

        $this->duplicateWarning = $duplicateAnalysis;
        $override = (bool) ($payload['duplicate_override'] ?? false);

        if ($duplicateAnalysis['risk'] === 'hard') {
            $this->addError('form.duplicate_override', 'Exact duplicate detected. This expense cannot be posted.');

            return false;
        }

        if (! $override) {
            $this->addError('form.duplicate_override', 'Possible duplicate found. Tick override to continue.');

            return false;
        }

        $this->resetErrorBag('form.duplicate_override');

        return true;
    }

    private function resetDuplicateWarningState(): void
    {
        $this->form['duplicate_override'] = false;
        $this->duplicateWarning = null;
        $this->resetErrorBag('form.duplicate_override');
    }

    public function attachmentDownloadUrlById(int $attachmentId): string
    {
        return route('expenses.attachments.download', ['attachment' => $attachmentId]);
    }

    private function loadExpenseForView(int $expenseId): Expense
    {
        return Expense::query()
            ->with([
                'department:id,name',
                'vendor:id,name',
                'paidBy:id,name',
                'creator:id,name',
                'voidedBy:id,name',
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
        $this->viewExpense = [
            'id' => $expense->id,
            'expense_code' => $expense->expense_code,
            'title' => $expense->title,
            'amount' => (int) $expense->amount,
            'expense_date' => $expense->expense_date?->format('M d, Y'),
            'status' => $expense->status,
            'source' => $expense->is_direct ? 'Direct expense' : 'Request-linked expense',
            'request_reference' => $expense->request_id ? 'REQ-'.$expense->request_id : '-',
            'department' => $expense->department?->name ?? '-',
            'vendor' => $expense->vendor?->name ?? 'Unlinked',
            'payment_method' => $expense->payment_method ? ucfirst($expense->payment_method) : 'Not specified',
            'created_by' => $expense->creator?->name ?? '-',
            'paid_by' => $expense->paidBy?->name ?? 'Unspecified',
            'voided_by' => $expense->voidedBy?->name ?? '-',
            'voided_at' => optional($expense->voided_at)->format('M d, Y H:i'),
            'void_reason' => $expense->void_reason ?: '-',
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


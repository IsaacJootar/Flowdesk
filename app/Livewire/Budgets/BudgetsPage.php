<?php

namespace App\Livewire\Budgets;

use App\Actions\Budgets\CloseDepartmentBudget;
use App\Actions\Budgets\CreateDepartmentBudget;
use App\Actions\Budgets\UpdateDepartmentBudget;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class BudgetsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $departmentFilter = 'all';

    public string $statusFilter = 'all';

    public string $periodTypeFilter = 'all';

    public int $perPage = 10;

    public bool $showFormModal = false;

    public bool $isEditing = false;

    public ?int $editingBudgetId = null;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public int $summaryAllocated = 0;

    public int $summarySpent = 0;

    public int $summaryRemaining = 0;

    /** @var array<int, array{spent:int,remaining:int}> */
    public array $budgetMetrics = [];

    /** @var array<string, mixed> */
    public array $form = [
        'department_id' => '',
        'period_type' => 'monthly',
        'period_start' => '',
        'period_end' => '',
        'allocated_amount' => '',
    ];

    public function mount(): void
    {
        $this->feedbackMessage = session('status');
        $this->form['period_start'] = now()->startOfMonth()->toDateString();
        $this->form['period_end'] = now()->endOfMonth()->toDateString();
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function updated(string $propertyName, mixed $value = null): void
    {
        if (str_starts_with($propertyName, 'form.')) {
            $this->resetErrorBag('form.no_changes');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function openCreateModal(): void
    {
        Gate::authorize('create', DepartmentBudget::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingBudgetId = null;
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function openEditModal(int $budgetId): void
    {
        $budget = $this->findBudgetOrFail($budgetId);
        Gate::authorize('update', $budget);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->isEditing = true;
        $this->editingBudgetId = $budget->id;
        $this->fillFormFromBudget($budget);
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->isEditing = false;
        $this->editingBudgetId = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function save(CreateDepartmentBudget $createBudget, UpdateDepartmentBudget $updateBudget): void
    {
        $this->feedbackError = null;
        $this->validate($this->formRules(), $this->formMessages());

        try {
            if ($this->isEditing && $this->editingBudgetId) {
                $budget = $this->findBudgetOrFail($this->editingBudgetId);
                $updateBudget(auth()->user(), $budget, $this->formPayload());
                $this->setFeedback('Budget updated successfully.');
            } else {
                $createBudget(auth()->user(), $this->formPayload());
                $this->setFeedback('Budget created successfully.');
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
            $this->setFeedbackError('Unable to save budget. Please try again.');
            return;
        }

        $this->closeFormModal();
        $this->resetPage();
    }

    /**
     * @throws AuthorizationException
     */
    public function closeBudget(int $budgetId, CloseDepartmentBudget $closeBudget): void
    {
        $this->feedbackError = null;
        $budget = $this->findBudgetOrFail($budgetId);

        try {
            $closeBudget(auth()->user(), $budget);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to close budget right now.');
            return;
        }

        $this->setFeedback('Budget closed successfully.');
        $this->resetPage();
    }

    public function getCanManageProperty(): bool
    {
        return Gate::allows('create', DepartmentBudget::class);
    }

    public function render(): View
    {
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        $budgets = $this->readyToLoad
            ? $this->budgetsQuery()->paginate($this->perPage)
            : DepartmentBudget::query()->whereRaw('1 = 0')->paginate($this->perPage);

        $this->budgetMetrics = [];
        foreach ($budgets as $budget) {
            $spent = $this->calculateSpent($budget);
            $remaining = (int) $budget->allocated_amount - $spent;
            $this->budgetMetrics[$budget->id] = [
                'spent' => $spent,
                'remaining' => $remaining,
            ];
        }

        $this->computeSummary();

        return view('livewire.budgets.budgets-page', [
            'departments' => $departments,
            'budgets' => $budgets,
            'periodTypes' => ['monthly', 'quarterly', 'yearly'],
        ]);
    }

    private function budgetsQuery()
    {
        return DepartmentBudget::query()
            ->with('department:id,name')
            ->when($this->search !== '', function ($query): void {
                $query->whereHas(
                    'department',
                    fn ($departmentQuery) => $departmentQuery->where('name', 'like', '%'.$this->search.'%')
                );
            })
            ->when($this->departmentFilter !== 'all', fn ($query) => $query->where('department_id', $this->departmentFilter))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->periodTypeFilter !== 'all', fn ($query) => $query->where('period_type', $this->periodTypeFilter))
            ->latest('period_start')
            ->latest('id');
    }

    private function computeSummary(): void
    {
        $this->summaryAllocated = 0;
        $this->summarySpent = 0;
        $this->summaryRemaining = 0;

        $activeBudgets = DepartmentBudget::query()
            ->where('status', 'active')
            ->get(['id', 'department_id', 'period_start', 'period_end', 'allocated_amount']);

        foreach ($activeBudgets as $budget) {
            $spent = $this->calculateSpent($budget);
            $remaining = (int) $budget->allocated_amount - $spent;
            $this->summaryAllocated += (int) $budget->allocated_amount;
            $this->summarySpent += $spent;
            $this->summaryRemaining += $remaining;
        }
    }

    private function calculateSpent(DepartmentBudget $budget): int
    {
        return (int) Expense::query()
            ->where('department_id', $budget->department_id)
            ->where('status', 'posted')
            ->whereDate('expense_date', '>=', $budget->period_start?->toDateString())
            ->whereDate('expense_date', '<=', $budget->period_end?->toDateString())
            ->sum('amount');
    }

    private function findBudgetOrFail(int $budgetId): DepartmentBudget
    {
        /** @var DepartmentBudget $budget */
        $budget = DepartmentBudget::query()->findOrFail($budgetId);

        return $budget;
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        return [
            'department_id' => (int) $this->form['department_id'],
            'period_type' => (string) $this->form['period_type'],
            'period_start' => (string) $this->form['period_start'],
            'period_end' => (string) $this->form['period_end'],
            'allocated_amount' => (int) $this->form['allocated_amount'],
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'department_id' => '',
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_amount' => '',
        ];
    }

    private function fillFormFromBudget(DepartmentBudget $budget): void
    {
        $this->form = [
            'department_id' => (string) $budget->department_id,
            'period_type' => (string) $budget->period_type,
            'period_start' => $budget->period_start?->toDateString() ?? '',
            'period_end' => $budget->period_end?->toDateString() ?? '',
            'allocated_amount' => (string) $budget->allocated_amount,
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

    /**
     * @return array<string, mixed>
     */
    private function formRules(): array
    {
        return [
            'form.department_id' => ['required', 'integer'],
            'form.period_type' => ['required', 'in:monthly,quarterly,yearly'],
            'form.period_start' => ['required', 'date'],
            'form.period_end' => ['required', 'date', 'after_or_equal:form.period_start'],
            'form.allocated_amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function formMessages(): array
    {
        return [
            'form.department_id.required' => 'Department is required.',
            'form.period_type.required' => 'Period type is required.',
            'form.period_start.required' => 'Period start date is required.',
            'form.period_end.required' => 'Period end date is required.',
            'form.period_end.after_or_equal' => 'Period end must be after or same as period start.',
            'form.allocated_amount.required' => 'Allocated amount is required.',
            'form.allocated_amount.min' => 'Allocated amount must be greater than zero.',
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        $mapped = [];
        $formFields = ['department_id', 'period_type', 'period_start', 'period_end', 'allocated_amount', 'status'];

        foreach ($errors as $key => $messages) {
            if (str_starts_with($key, 'form.')) {
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
}

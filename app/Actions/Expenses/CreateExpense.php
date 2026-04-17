<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExpenseBudgetGuardrail;
use App\Services\ExpenseCodeGenerator;
use App\Services\ExpenseDuplicateDetector;
use App\Services\ExpensePolicyResolver;
use App\Services\SpendLifecycleControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateExpense
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ExpenseCodeGenerator $expenseCodeGenerator,
        private readonly ExpenseBudgetGuardrail $expenseBudgetGuardrail,
        private readonly ExpenseDuplicateDetector $expenseDuplicateDetector,
        private readonly ExpensePolicyResolver $expensePolicyResolver,
        private readonly SpendLifecycleControlService $spendLifecycleControlService
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): Expense
    {
        Gate::forUser($user)->authorize('create', Expense::class);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'User must belong to a company before creating expenses.',
            ]);
        }

        $validated = Validator::make($input, $this->rules((int) $user->company_id, $input))->validate();
        // Duplicate check runs before write so we can block hard matches or require override for soft matches.
        $duplicateAnalysis = $this->expenseDuplicateDetector->analyze((int) $user->company_id, $validated);
        $duplicateOverride = (bool) ($validated['duplicate_override'] ?? false);
        $this->enforceDuplicateRules(
            user: $user,
            duplicateAnalysis: $duplicateAnalysis,
            duplicateOverride: $duplicateOverride
        );

        $isDirect = array_key_exists('is_direct', $validated) ? (bool) $validated['is_direct'] : true;
        $requestId = $isDirect ? null : (int) ($validated['request_id'] ?? 0);
        $permissionDecision = $isDirect
            ? $this->expensePolicyResolver->canCreateDirect(
                user: $user,
                departmentId: (int) $validated['department_id'],
                amount: (int) $validated['amount']
            )
            : $this->expensePolicyResolver->canCreateFromRequest(
                user: $user,
                departmentId: (int) $validated['department_id'],
                amount: (int) $validated['amount']
            );

        if (! $permissionDecision['allowed']) {
            throw ValidationException::withMessages([
                'authorization' => (string) ($permissionDecision['reason'] ?? 'You are not allowed to post this expense.'),
            ]);
        }

        if ($isDirect && $this->spendLifecycleControlService->directExpenseReasonRequired((int) $user->company_id) && trim((string) ($validated['description'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'description' => 'Enter a reason for this direct expense.',
            ]);
        }

        // Budget guardrail evaluates the target period before we insert a posted expense.
        $budgetGuardrail = $this->expenseBudgetGuardrail->evaluate(
            companyId: (int) $user->company_id,
            departmentId: (int) $validated['department_id'],
            expenseDate: (string) $validated['expense_date'],
            incomingAmount: (int) $validated['amount'],
        );
        $budgetDecision = $this->spendLifecycleControlService->budgetDecision(
            companyId: (int) $user->company_id,
            guardrail: $budgetGuardrail,
            context: 'expense_posting',
        );

        if (! $budgetDecision['allowed']) {
            $this->activityLogger->log(
                action: 'expense.budget.blocked',
                entityType: Expense::class,
                metadata: [
                    'title' => trim($validated['title']),
                    'amount' => (int) $validated['amount'],
                    'department_id' => (int) $validated['department_id'],
                    'is_direct' => $isDirect,
                    'budget_guardrail' => $budgetGuardrail,
                    'budget_decision' => $budgetDecision,
                ],
                companyId: (int) $user->company_id,
                userId: $user->id,
            );

            throw ValidationException::withMessages([
                'amount' => (string) $budgetDecision['message'],
            ]);
        }

        if ($budgetGuardrail['has_budget'] && $budgetGuardrail['is_blocked']) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Budget limit exceeded by NGN %s for this department period.',
                    number_format($budgetGuardrail['over_amount'])
                ),
            ]);
        }

        $directExpenseControls = [
            'receipt_required' => $isDirect
                ? $this->spendLifecycleControlService->directExpenseReceiptRequired((int) $user->company_id, (int) $validated['amount'])
                : false,
            'receipt_mode' => (string) ($this->spendLifecycleControlService->settingsForCompany((int) $user->company_id)['direct_expense_receipt_mode'] ?? SpendLifecycleControlService::RECEIPT_OPTIONAL),
            'reason_required' => $isDirect
                ? $this->spendLifecycleControlService->directExpenseReasonRequired((int) $user->company_id)
                : false,
        ];

        $expense = DB::transaction(function () use ($user, $validated, $requestId, $isDirect): Expense {
            // Create is wrapped in transaction so code generation/write and audit expectations remain atomic.
            return Expense::query()->create([
                'company_id' => $user->company_id,
                'expense_code' => $this->expenseCodeGenerator->generateForCompany((int) $user->company_id),
                'request_id' => $requestId > 0 ? $requestId : null,
                'department_id' => (int) $validated['department_id'],
                'vendor_id' => $validated['vendor_id'] ? (int) $validated['vendor_id'] : null,
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? null,
                'amount' => (int) $validated['amount'],
                'expense_date' => $validated['expense_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'paid_by_user_id' => $validated['paid_by_user_id'] ? (int) $validated['paid_by_user_id'] : null,
                'created_by' => $user->id,
                'status' => 'posted',
                'is_direct' => $isDirect,
            ]);
        });

        $this->activityLogger->log(
            action: 'expense.created',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'expense_code' => $expense->expense_code,
                'amount' => $expense->amount,
                'request_id' => $expense->request_id,
                'department_id' => $expense->department_id,
                'vendor_id' => $expense->vendor_id,
                'payment_method' => $expense->payment_method,
                'is_direct' => $expense->is_direct,
                'budget_guardrail' => $budgetGuardrail,
                'budget_decision' => $budgetDecision,
                'direct_expense_controls' => $directExpenseControls,
                'duplicate_detection' => [
                    'risk' => $duplicateAnalysis['risk'],
                    'override_used' => $duplicateOverride,
                    'matches_count' => count($duplicateAnalysis['matches']),
                ],
            ],
            companyId: $expense->company_id,
            userId: $user->id,
        );

        if ($duplicateAnalysis['risk'] === 'soft' && $duplicateOverride) {
            $this->activityLogger->log(
                action: 'expense.duplicate.overridden',
                entityType: Expense::class,
                entityId: $expense->id,
                metadata: [
                    'risk' => $duplicateAnalysis['risk'],
                    'matches' => $duplicateAnalysis['matches'],
                ],
                companyId: $expense->company_id,
                userId: $user->id,
            );
        }

        return $expense;
    }

    private function rules(int $companyId, array $input): array
    {
        return [
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'vendor_id' => [
                'nullable',
                Rule::exists('vendors', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'pos', 'online', 'cheque'])],
            'paid_by_user_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'is_direct' => ['nullable', 'boolean'],
            'duplicate_override' => ['nullable', 'boolean'],
            'request_id' => [
                Rule::requiredIf(
                    fn () => array_key_exists('is_direct', $input)
                        && (bool) ($input['is_direct'] ?? true) === false
                ),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * @param  array{risk: 'none'|'soft'|'hard', matches: array<int, array{id: int, expense_code: string, title: string, amount: int, expense_date: string|null}>}  $duplicateAnalysis
     *
     * @throws ValidationException
     */
    private function enforceDuplicateRules(User $user, array $duplicateAnalysis, bool $duplicateOverride): void
    {
        if ($duplicateAnalysis['risk'] === 'hard') {
            $this->activityLogger->log(
                action: 'expense.duplicate.blocked',
                entityType: Expense::class,
                metadata: [
                    'risk' => 'hard',
                    'matches' => $duplicateAnalysis['matches'],
                ],
                companyId: (int) $user->company_id,
                userId: $user->id,
            );

            throw ValidationException::withMessages([
                'duplicate_override' => 'Exact duplicate detected (same date, vendor, amount, and title). Posting is blocked.',
            ]);
        }

        if ($duplicateAnalysis['risk'] === 'soft' && ! $duplicateOverride) {
            $this->activityLogger->log(
                action: 'expense.duplicate.review_required',
                entityType: Expense::class,
                metadata: [
                    'risk' => 'soft',
                    'matches' => $duplicateAnalysis['matches'],
                ],
                companyId: (int) $user->company_id,
                userId: $user->id,
            );

            throw ValidationException::withMessages([
                'duplicate_override' => 'Possible duplicate found. Review the matches and tick override to continue.',
            ]);
        }
    }
}

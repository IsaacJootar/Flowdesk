<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExpenseBudgetGuardrail;
use App\Services\ExpenseDuplicateDetector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateExpense
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ExpenseBudgetGuardrail $expenseBudgetGuardrail,
        private readonly ExpenseDuplicateDetector $expenseDuplicateDetector
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Expense $expense, array $input): Expense
    {
        Gate::forUser($user)->authorize('update', $expense);

        if ($expense->status === 'void') {
            throw ValidationException::withMessages([
                'status' => 'Void expenses cannot be edited.',
            ]);
        }

        $validated = Validator::make($input, $this->rules((int) $expense->company_id, $input))->validate();
        $duplicateAnalysis = $this->expenseDuplicateDetector->analyze(
            companyId: (int) $expense->company_id,
            input: $validated,
            excludeExpenseId: $expense->id
        );
        $duplicateOverride = (bool) ($validated['duplicate_override'] ?? false);
        $this->enforceDuplicateRules(
            user: $user,
            expense: $expense,
            duplicateAnalysis: $duplicateAnalysis,
            duplicateOverride: $duplicateOverride
        );

        $isDirect = array_key_exists('is_direct', $validated) ? (bool) $validated['is_direct'] : (bool) $expense->is_direct;
        $requestId = $isDirect ? null : (int) ($validated['request_id'] ?? 0);
        $budgetGuardrail = $this->expenseBudgetGuardrail->enforceOrFail(
            companyId: (int) $expense->company_id,
            departmentId: (int) $validated['department_id'],
            expenseDate: (string) $validated['expense_date'],
            incomingAmount: (int) $validated['amount'],
            editingExpense: $expense
        );

        $before = $expense->only([
            'request_id',
            'department_id',
            'vendor_id',
            'title',
            'description',
            'amount',
            'expense_date',
            'payment_method',
            'paid_by_user_id',
            'is_direct',
        ]);

        $expense->fill([
            'request_id' => $requestId > 0 ? $requestId : null,
            'department_id' => (int) $validated['department_id'],
            'vendor_id' => $validated['vendor_id'] ? (int) $validated['vendor_id'] : null,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'amount' => (int) $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'paid_by_user_id' => $validated['paid_by_user_id'] ? (int) $validated['paid_by_user_id'] : null,
            'is_direct' => $isDirect,
        ]);

        if (! $expense->isDirty()) {
            throw ValidationException::withMessages([
                'no_changes' => 'No changes made. Update at least one field before saving.',
            ]);
        }

        $expense->save();

        $after = $expense->only(array_keys($before));

        $this->activityLogger->log(
            action: 'expense.updated',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'before' => $before,
                'after' => $after,
                'budget_guardrail' => $budgetGuardrail,
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
    private function enforceDuplicateRules(User $user, Expense $expense, array $duplicateAnalysis, bool $duplicateOverride): void
    {
        if ($duplicateAnalysis['risk'] === 'hard') {
            $this->activityLogger->log(
                action: 'expense.duplicate.blocked',
                entityType: Expense::class,
                entityId: $expense->id,
                metadata: [
                    'risk' => 'hard',
                    'matches' => $duplicateAnalysis['matches'],
                ],
                companyId: (int) $expense->company_id,
                userId: $user->id,
            );

            throw ValidationException::withMessages([
                'duplicate_override' => 'Exact duplicate detected (same date, vendor, amount, and title). Update is blocked.',
            ]);
        }

        if ($duplicateAnalysis['risk'] === 'soft' && ! $duplicateOverride) {
            $this->activityLogger->log(
                action: 'expense.duplicate.review_required',
                entityType: Expense::class,
                entityId: $expense->id,
                metadata: [
                    'risk' => 'soft',
                    'matches' => $duplicateAnalysis['matches'],
                ],
                companyId: (int) $expense->company_id,
                userId: $user->id,
            );

            throw ValidationException::withMessages([
                'duplicate_override' => 'Possible duplicate found. Review the matches and tick override to continue.',
            ]);
        }
    }
}

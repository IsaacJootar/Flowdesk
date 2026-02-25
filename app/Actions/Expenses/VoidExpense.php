<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExpensePolicyResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VoidExpense
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ExpensePolicyResolver $expensePolicyResolver
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Expense $expense, array $input): Expense
    {
        Gate::forUser($user)->authorize('void', $expense);

        if ($expense->status === 'void') {
            throw ValidationException::withMessages([
                'status' => 'Expense is already void.',
            ]);
        }

        $permissionDecision = $this->expensePolicyResolver->canVoid(
            user: $user,
            departmentId: $expense->department_id ? (int) $expense->department_id : null,
            amount: (int) $expense->amount
        );
        if (! $permissionDecision['allowed']) {
            throw ValidationException::withMessages([
                'authorization' => (string) ($permissionDecision['reason'] ?? 'You are not allowed to void this expense.'),
            ]);
        }

        $validated = Validator::make($input, [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ])->validate();

        $reason = trim($validated['reason']);

        // Keep original record; mark as void so finance history remains intact.
        $expense->forceFill([
            'status' => 'void',
            'voided_by' => $user->id,
            'voided_at' => now(),
            'void_reason' => $reason,
        ])->save();

        $this->activityLogger->log(
            action: 'expense.voided',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'expense_code' => $expense->expense_code,
                'reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => optional($expense->voided_at)?->toIso8601String(),
            ],
            companyId: $expense->company_id,
            userId: $user->id,
        );

        return $expense;
    }
}

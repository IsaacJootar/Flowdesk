<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VoidExpense
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

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

        $validated = Validator::make($input, [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ])->validate();

        $expense->forceFill([
            'status' => 'void',
        ])->save();

        $this->activityLogger->log(
            action: 'expense.voided',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'expense_code' => $expense->expense_code,
                'reason' => trim($validated['reason']),
            ],
            companyId: $expense->company_id,
            userId: $user->id,
        );

        return $expense;
    }
}

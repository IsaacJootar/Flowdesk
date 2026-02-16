<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateExpense
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

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

        $validated = Validator::make($input, $this->rules((int) $expense->company_id))->validate();

        $before = $expense->only([
            'department_id',
            'vendor_id',
            'title',
            'description',
            'amount',
            'expense_date',
            'payment_method',
            'paid_by_user_id',
        ]);

        $expense->fill([
            'department_id' => (int) $validated['department_id'],
            'vendor_id' => $validated['vendor_id'] ? (int) $validated['vendor_id'] : null,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'amount' => (int) $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'paid_by_user_id' => $validated['paid_by_user_id'] ? (int) $validated['paid_by_user_id'] : null,
        ])->save();

        $after = $expense->only(array_keys($before));

        $this->activityLogger->log(
            action: 'expense.updated',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'before' => $before,
                'after' => $after,
            ],
            companyId: $expense->company_id,
            userId: $user->id,
        );

        return $expense;
    }

    private function rules(int $companyId): array
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
        ];
    }
}

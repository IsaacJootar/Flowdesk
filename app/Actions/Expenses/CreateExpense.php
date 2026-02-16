<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExpenseCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateExpense
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ExpenseCodeGenerator $expenseCodeGenerator
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

        $validated = Validator::make($input, $this->rules((int) $user->company_id))->validate();

        $expense = DB::transaction(function () use ($user, $validated): Expense {
            return Expense::query()->create([
                'company_id' => $user->company_id,
                'expense_code' => $this->expenseCodeGenerator->generateForCompany((int) $user->company_id),
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
                'is_direct' => true,
            ]);
        });

        $this->activityLogger->log(
            action: 'expense.created',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'expense_code' => $expense->expense_code,
                'amount' => $expense->amount,
                'department_id' => $expense->department_id,
                'vendor_id' => $expense->vendor_id,
                'payment_method' => $expense->payment_method,
                'is_direct' => $expense->is_direct,
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

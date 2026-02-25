<?php

namespace App\Policies;

use App\Domains\Expenses\Models\Expense;
use App\Services\ExpensePolicyResolver;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->sameCompany($user, $expense);
    }

    public function create(User $user): bool
    {
        return app(ExpensePolicyResolver::class)->canCreateAny($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        if (! $this->sameCompany($user, $expense) || $expense->status === 'void') {
            return false;
        }

        return app(ExpensePolicyResolver::class)
            ->canEditPosted(
                user: $user,
                departmentId: $expense->department_id ? (int) $expense->department_id : null,
                amount: (int) $expense->amount
            )['allowed'];
    }

    public function void(User $user, Expense $expense): bool
    {
        if (! $this->sameCompany($user, $expense) || $expense->status === 'void') {
            return false;
        }

        return app(ExpensePolicyResolver::class)
            ->canVoid(
                user: $user,
                departmentId: $expense->department_id ? (int) $expense->department_id : null,
                amount: (int) $expense->amount
            )['allowed'];
    }

    public function uploadAttachment(User $user, Expense $expense): bool
    {
        if (! $this->sameCompany($user, $expense) || $expense->status === 'void') {
            return false;
        }

        return app(ExpensePolicyResolver::class)
            ->canEditPosted(
                user: $user,
                departmentId: $expense->department_id ? (int) $expense->department_id : null,
                amount: (int) $expense->amount
            )['allowed'];
    }

    public function delete(User $user, Expense $expense): bool
    {
        return false;
    }

    private function sameCompany(User $user, object $model): bool
    {
        return (bool) $user->is_active
            && (int) $user->company_id === (int) ($model->company_id ?? 0);
    }
}

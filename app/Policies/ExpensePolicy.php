<?php

namespace App\Policies;

use App\Domains\Expenses\Models\Expense;
use App\Enums\UserRole;
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
        return $user->is_active && $this->canManageExpenses($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->sameCompany($user, $expense)
            && $this->canManageExpenses($user)
            && $expense->status !== 'void';
    }

    public function void(User $user, Expense $expense): bool
    {
        return $this->sameCompany($user, $expense)
            && $this->canManageExpenses($user)
            && $expense->status !== 'void';
    }

    public function uploadAttachment(User $user, Expense $expense): bool
    {
        return $this->sameCompany($user, $expense) && $this->canManageExpenses($user);
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

    private function canManageExpenses(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner->value, UserRole::Finance->value], true);
    }
}

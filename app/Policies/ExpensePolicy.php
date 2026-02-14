<?php

namespace App\Policies;

use App\Domains\Expenses\Models\Expense;
use App\Enums\UserRole;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->sameCompany($user, $expense);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, [UserRole::Owner, UserRole::Finance, UserRole::Manager, UserRole::Staff]);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->sameCompany($user, $expense) && $this->hasAnyRole($user, [UserRole::Owner, UserRole::Finance]);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }

    private function sameCompany(User $user, object $model): bool
    {
        return (int) $user->company_id === (int) ($model->company_id ?? 0);
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        return in_array($user->role, array_map(fn (UserRole $role): string => $role->value, $roles), true);
    }
}

<?php

namespace App\Policies;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Enums\UserRole;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, DepartmentBudget $budget): bool
    {
        return (int) $user->company_id === (int) ($budget->company_id ?? 0);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner->value, UserRole::Finance->value], true);
    }

    public function update(User $user, DepartmentBudget $budget): bool
    {
        return $this->view($user, $budget) && $this->create($user);
    }

    public function delete(User $user, DepartmentBudget $budget): bool
    {
        return $this->update($user, $budget);
    }
}

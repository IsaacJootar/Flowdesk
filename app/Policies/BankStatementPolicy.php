<?php

namespace App\Policies;

use App\Domains\Treasury\Models\BankStatement;
use App\Enums\UserRole;
use App\Models\User;

class BankStatementPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->is_active || ! $user->company_id) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    public function view(User $user, BankStatement $statement): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $statement->company_id;
    }

    public function operate(User $user): bool
    {
        if (! $user->is_active || ! $user->company_id) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }
}


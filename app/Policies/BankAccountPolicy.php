<?php

namespace App\Policies;

use App\Domains\Treasury\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return app(BankStatementPolicy::class)->viewAny($user);
    }

    public function view(User $user, BankAccount $account): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $account->company_id;
    }

    public function manage(User $user): bool
    {
        return app(BankStatementPolicy::class)->operate($user);
    }
}


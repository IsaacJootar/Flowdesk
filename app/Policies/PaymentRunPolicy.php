<?php

namespace App\Policies;

use App\Domains\Treasury\Models\PaymentRun;
use App\Models\User;

class PaymentRunPolicy
{
    public function viewAny(User $user): bool
    {
        return app(BankStatementPolicy::class)->viewAny($user);
    }

    public function view(User $user, PaymentRun $run): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $run->company_id;
    }
}


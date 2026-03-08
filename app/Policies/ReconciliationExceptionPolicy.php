<?php

namespace App\Policies;

use App\Domains\Treasury\Models\ReconciliationException;
use App\Models\User;
use App\Services\Treasury\TreasuryControlSettingsService;

class ReconciliationExceptionPolicy
{
    public function __construct(
        private readonly TreasuryControlSettingsService $settingsService
    ) {
    }

    public function viewAny(User $user): bool
    {
        return app(BankStatementPolicy::class)->viewAny($user);
    }

    public function view(User $user, ReconciliationException $exception): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $exception->company_id;
    }

    public function resolveAny(User $user): bool
    {
        if (! $user->is_active || ! $user->company_id) {
            return false;
        }

        $controls = $this->settingsService->effectiveControls((int) $user->company_id);
        $allowedRoles = array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            (array) ($controls['exception_action_allowed_roles'] ?? ['owner', 'finance'])
        )));

        return in_array(strtolower((string) $user->role), $allowedRoles, true);
    }

    public function resolve(User $user, ReconciliationException $exception): bool
    {
        return $this->view($user, $exception) && $this->resolveAny($user);
    }
}


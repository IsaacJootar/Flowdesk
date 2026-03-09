<?php

namespace App\Policies;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Enums\UserRole;
use App\Models\User;

class RequestPayoutExecutionAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $this->isTenantUser($user)) {
            return false;
        }

        return $this->hasAnyRole($user, [
            UserRole::Owner,
            UserRole::Finance,
            UserRole::Manager,
            UserRole::Auditor,
        ]);
    }

    public function view(User $user, RequestPayoutExecutionAttempt $attempt): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $attempt->company_id;
    }

    public function queueAny(User $user): bool
    {
        if (! $this->isTenantUser($user)) {
            return false;
        }

        return $this->hasAnyRole($user, [
            UserRole::Owner,
            UserRole::Finance,
            UserRole::Manager,
        ]);
    }

    private function isTenantUser(User $user): bool
    {
        return $user->is_active && (int) $user->company_id > 0;
    }

    /**
     * @param  array<int, UserRole>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        return in_array(
            (string) $user->role,
            array_map(static fn (UserRole $role): string => $role->value, $roles),
            true
        );
    }
}

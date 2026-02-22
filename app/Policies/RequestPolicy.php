<?php

namespace App\Policies;

use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RequestApprovalRouter;

class RequestPolicy
{
    public function __construct(
        private readonly RequestApprovalRouter $requestApprovalRouter
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, SpendRequest $request): bool
    {
        if (! $this->sameCompany($user, $request)) {
            return false;
        }

        if ($this->hasAnyRole($user, [UserRole::Owner, UserRole::Finance, UserRole::Auditor])) {
            return true;
        }

        if ($user->role === UserRole::Manager->value) {
            return (int) $user->department_id === (int) $request->department_id;
        }

        return (int) $request->requested_by === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, [UserRole::Owner, UserRole::Finance, UserRole::Manager, UserRole::Staff]);
    }

    public function update(User $user, SpendRequest $request): bool
    {
        return $this->view($user, $request);
    }

    public function delete(User $user, SpendRequest $request): bool
    {
        return $this->hasAnyRole($user, [UserRole::Owner, UserRole::Finance]) && $this->sameCompany($user, $request);
    }

    public function approve(User $user, SpendRequest $request): bool
    {
        if (! $this->sameCompany($user, $request)) {
            return false;
        }

        if ($this->requestApprovalRouter->hasConfiguredWorkflow($request)) {
            return $this->requestApprovalRouter->canApprove($user, $request);
        }

        return $this->hasAnyRole($user, [UserRole::Owner, UserRole::Finance])
            || ($user->role === UserRole::Manager->value && (int) $user->department_id === (int) $request->department_id);
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

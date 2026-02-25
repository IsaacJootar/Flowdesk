<?php

namespace App\Services;

use App\Domains\Expenses\Models\CompanyExpensePolicySetting;
use App\Models\User;

class ExpensePolicyResolver
{
    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canCreateDirect(User $user, ?int $departmentId = null, ?int $amount = null): array
    {
        return $this->evaluate(
            user: $user,
            action: CompanyExpensePolicySetting::ACTION_CREATE_DIRECT,
            departmentId: $departmentId,
            amount: $amount
        );
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canCreateFromRequest(User $user, ?int $departmentId = null, ?int $amount = null): array
    {
        return $this->evaluate(
            user: $user,
            action: CompanyExpensePolicySetting::ACTION_CREATE_FROM_REQUEST,
            departmentId: $departmentId,
            amount: $amount
        );
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canEditPosted(User $user, ?int $departmentId = null, ?int $amount = null): array
    {
        return $this->evaluate(
            user: $user,
            action: CompanyExpensePolicySetting::ACTION_EDIT_POSTED,
            departmentId: $departmentId,
            amount: $amount
        );
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canVoid(User $user, ?int $departmentId = null, ?int $amount = null): array
    {
        return $this->evaluate(
            user: $user,
            action: CompanyExpensePolicySetting::ACTION_VOID,
            departmentId: $departmentId,
            amount: $amount
        );
    }

    public function canCreateAny(User $user): bool
    {
        if (! $user->is_active || ! $user->company_id) {
            return false;
        }

        return $this->roleAllowedForAction($user, CompanyExpensePolicySetting::ACTION_CREATE_DIRECT)
            || $this->roleAllowedForAction($user, CompanyExpensePolicySetting::ACTION_CREATE_FROM_REQUEST);
    }

    public function settingsForCompany(int $companyId): CompanyExpensePolicySetting
    {
        return CompanyExpensePolicySetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                array_merge(
                    CompanyExpensePolicySetting::defaultAttributes(),
                    ['created_by' => \Illuminate\Support\Facades\Auth::id()]
                )
            );
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    private function evaluate(User $user, string $action, ?int $departmentId = null, ?int $amount = null): array
    {
        if (! $user->is_active || ! $user->company_id) {
            return ['allowed' => false, 'reason' => 'Inactive users cannot run expense actions.'];
        }

        $policy = $this->settingsForCompany((int) $user->company_id)->policyForAction($action);
        $allowedRoles = (array) ($policy['allowed_roles'] ?? []);
        if (! in_array((string) $user->role, $allowedRoles, true)) {
            return ['allowed' => false, 'reason' => 'Your role is not allowed for this expense action.'];
        }

        $restrictedDepartments = array_map('intval', (array) ($policy['department_ids'] ?? []));
        if ($departmentId && $restrictedDepartments !== [] && ! in_array((int) $departmentId, $restrictedDepartments, true)) {
            return ['allowed' => false, 'reason' => 'Your role is not allowed to post for this department.'];
        }

        $roleLimit = (int) (((array) ($policy['amount_limits'] ?? []))[(string) $user->role] ?? 0);
        $requiresSecondary = (bool) ($policy['require_secondary_approval_over_limit'] ?? false);
        if ($amount !== null && $amount > 0 && $roleLimit > 0 && $amount > $roleLimit && $requiresSecondary) {
            return [
                'allowed' => false,
                'reason' => 'Amount exceeds your role threshold. Submit request approval before posting.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    private function roleAllowedForAction(User $user, string $action): bool
    {
        $policy = $this->settingsForCompany((int) $user->company_id)->policyForAction($action);

        return in_array((string) $user->role, (array) ($policy['allowed_roles'] ?? []), true);
    }
}


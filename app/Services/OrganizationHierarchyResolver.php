<?php

namespace App\Services;

use App\Domains\Company\Models\Department;
use App\Models\User;

class OrganizationHierarchyResolver
{
    public function resolveManagerForUserId(int $userId, int $companyId): ?User
    {
        $requester = User::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->where('is_active', true)
            ->first();

        if (! $requester) {
            return null;
        }

        if ($requester->reports_to_user_id) {
            $lineManager = User::query()
                ->where('company_id', $companyId)
                ->where('id', (int) $requester->reports_to_user_id)
                ->where('is_active', true)
                ->first();

            if ($lineManager) {
                return $lineManager;
            }
        }

        return $this->resolveDepartmentHead(
            departmentId: $requester->department_id ? (int) $requester->department_id : null,
            companyId: $companyId
        );
    }

    public function resolveDepartmentHead(?int $departmentId, int $companyId): ?User
    {
        if (! $departmentId) {
            return null;
        }

        $department = Department::query()
            ->where('company_id', $companyId)
            ->where('id', $departmentId)
            ->where('is_active', true)
            ->first();

        if (! $department || ! $department->manager_user_id) {
            return null;
        }

        return User::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $department->manager_user_id)
            ->where('is_active', true)
            ->first();
    }
}


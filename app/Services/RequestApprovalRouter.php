<?php

namespace App\Services;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class RequestApprovalRouter
{
    public function __construct(
        private readonly OrganizationHierarchyResolver $organizationHierarchyResolver
    ) {
    }

    public function hasConfiguredWorkflow(SpendRequest $request): bool
    {
        $workflow = $this->resolveActiveWorkflow($request);

        if (! $workflow) {
            return false;
        }

        return ApprovalWorkflowStep::query()
            ->where('company_id', $request->company_id)
            ->where('workflow_id', $workflow->id)
            ->where('is_active', true)
            ->exists();
    }

    public function canApprove(User $actor, SpendRequest $request): bool
    {
        if ((int) $actor->company_id !== (int) $request->company_id) {
            return false;
        }

        $step = $this->resolveCurrentStep($request);

        if (! $step) {
            return false;
        }

        $eligibleUsers = $this->resolveEligibleApprovers($step, $request);

        return $eligibleUsers->contains(fn (User $user): bool => (int) $user->id === (int) $actor->id);
    }

    public function resolveActiveWorkflow(SpendRequest $request): ?ApprovalWorkflow
    {
        if ($request->workflow_id) {
            $workflow = ApprovalWorkflow::query()
                ->where('company_id', $request->company_id)
                ->where('id', (int) $request->workflow_id)
                ->where('is_active', true)
                ->first();

            if ($workflow) {
                return $workflow;
            }
        }

        return ApprovalWorkflow::query()
            ->where('company_id', $request->company_id)
            ->where('applies_to', 'request')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function resolveCurrentStep(SpendRequest $request): ?ApprovalWorkflowStep
    {
        $workflow = $this->resolveActiveWorkflow($request);

        if (! $workflow) {
            return null;
        }

        $requestedStepOrder = (int) ($request->current_approval_step ?? 1);
        if ($requestedStepOrder < 1) {
            $requestedStepOrder = 1;
        }

        $exactStep = ApprovalWorkflowStep::query()
            ->where('company_id', $request->company_id)
            ->where('workflow_id', $workflow->id)
            ->where('is_active', true)
            ->where('step_order', $requestedStepOrder)
            ->first();

        if ($exactStep) {
            return $exactStep;
        }

        return ApprovalWorkflowStep::query()
            ->where('company_id', $request->company_id)
            ->where('workflow_id', $workflow->id)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveEligibleApprovers(ApprovalWorkflowStep $step, SpendRequest $request): Collection
    {
        return match ($step->actor_type) {
            'reports_to' => $this->resolveReportsToApprover($request),
            'department_manager' => $this->resolveDepartmentManagerApprover($request),
            'role' => $this->resolveRoleApprovers($request, $step->actor_value),
            'user' => $this->resolveExplicitUserApprover($request, $step->actor_value),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveReportsToApprover(SpendRequest $request): Collection
    {
        $manager = $this->organizationHierarchyResolver->resolveManagerForUserId(
            userId: (int) $request->requested_by,
            companyId: (int) $request->company_id
        );

        return $manager ? collect([$manager]) : collect();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveDepartmentManagerApprover(SpendRequest $request): Collection
    {
        $manager = $this->organizationHierarchyResolver->resolveDepartmentHead(
            departmentId: $request->department_id ? (int) $request->department_id : null,
            companyId: (int) $request->company_id
        );

        return $manager ? collect([$manager]) : collect();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRoleApprovers(SpendRequest $request, ?string $role): Collection
    {
        if (! $role) {
            return collect();
        }

        return User::query()
            ->where('company_id', $request->company_id)
            ->where('role', $role)
            ->where('is_active', true)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveExplicitUserApprover(SpendRequest $request, ?string $userId): Collection
    {
        if (! $userId || ! is_numeric($userId)) {
            return collect();
        }

        $user = User::query()
            ->where('company_id', $request->company_id)
            ->where('id', (int) $userId)
            ->where('is_active', true)
            ->first();

        return $user ? collect([$user]) : collect();
    }
}

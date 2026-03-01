<?php

namespace App\Services;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class RequestApprovalRouter
{
    public const SCOPE_REQUEST = 'request';

    public const SCOPE_PAYMENT_AUTHORIZATION = 'payment_authorization';

    public function __construct(
        private readonly OrganizationHierarchyResolver $organizationHierarchyResolver,
        private readonly PaymentAuthorizationWorkflowResolver $paymentAuthorizationWorkflowResolver,
    ) {
    }

    public function hasConfiguredWorkflow(SpendRequest $request): bool
    {
        $workflow = $this->resolveActiveWorkflow($request);

        if (! $workflow) {
            return false;
        }

        return ApprovalWorkflowStep::withoutGlobalScopes()
            ->where('company_id', (int) $request->company_id)
            ->where('workflow_id', (int) $workflow->id)
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

    public function currentScope(SpendRequest $request): string
    {
        $scope = strtolower(trim((string) data_get((array) ($request->metadata ?? []), 'approval_scope', self::SCOPE_REQUEST)));

        return in_array($scope, [self::SCOPE_REQUEST, self::SCOPE_PAYMENT_AUTHORIZATION], true)
            ? $scope
            : self::SCOPE_REQUEST;
    }

    public function resolveActiveWorkflow(SpendRequest $request): ?ApprovalWorkflow
    {
        return $this->currentScope($request) === self::SCOPE_PAYMENT_AUTHORIZATION
            ? $this->resolveActivePaymentAuthorizationWorkflow($request)
            : $this->resolveActiveRequestWorkflow($request);
    }

    public function resolveCurrentStep(SpendRequest $request): ?ApprovalWorkflowStep
    {
        $applicableSteps = $this->resolveApplicableSteps($request);
        if ($applicableSteps->isEmpty()) {
            return null;
        }

        $requestedStepOrder = (int) ($request->current_approval_step ?? 0);
        if ($requestedStepOrder > 0) {
            $exact = $applicableSteps->first(
                fn (ApprovalWorkflowStep $step): bool => (int) $step->step_order === $requestedStepOrder
            );
            if ($exact) {
                return $exact;
            }
        }

        return $applicableSteps->first();
    }

    public function resolveNextStep(SpendRequest $request, int $afterStepOrder): ?ApprovalWorkflowStep
    {
        return $this->resolveApplicableSteps($request)
            ->first(fn (ApprovalWorkflowStep $step): bool => (int) $step->step_order > $afterStepOrder);
    }

    /**
     * @return Collection<int, ApprovalWorkflowStep>
     */
    public function resolveApplicableSteps(SpendRequest $request): Collection
    {
        if ($this->currentScope($request) === self::SCOPE_PAYMENT_AUTHORIZATION) {
            return $this->paymentAuthorizationWorkflowResolver->resolveApplicableSteps(
                companyId: (int) $request->company_id,
                amount: (int) ($request->approved_amount ?: $request->amount)
            );
        }

        $workflow = $this->resolveActiveRequestWorkflow($request);
        if (! $workflow) {
            return collect();
        }

        $amount = (int) $request->amount;

        // Amount thresholds are enforced here so approval chains adapt per request value.
        return ApprovalWorkflowStep::withoutGlobalScopes()
            ->where('company_id', (int) $request->company_id)
            ->where('workflow_id', (int) $workflow->id)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->get()
            ->filter(fn (ApprovalWorkflowStep $step): bool => $this->stepAppliesToAmount($step, $amount))
            ->values();
    }

    private function resolveActiveRequestWorkflow(SpendRequest $request): ?ApprovalWorkflow
    {
        if ($request->workflow_id) {
            $workflow = ApprovalWorkflow::withoutGlobalScopes()
                ->where('company_id', (int) $request->company_id)
                ->where('id', (int) $request->workflow_id)
                ->where('applies_to', ApprovalWorkflow::APPLIES_TO_REQUEST)
                ->where('is_active', true)
                ->first();

            if ($workflow) {
                return $workflow;
            }
        }

        // Fallback keeps requests routable when a draft has no explicit workflow_id.
        return ApprovalWorkflow::withoutGlobalScopes()
            ->where('company_id', (int) $request->company_id)
            ->where('applies_to', ApprovalWorkflow::APPLIES_TO_REQUEST)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function resolveActivePaymentAuthorizationWorkflow(SpendRequest $request): ?ApprovalWorkflow
    {
        return $this->paymentAuthorizationWorkflowResolver->resolveDefaultWorkflow((int) $request->company_id);
    }

    private function stepAppliesToAmount(ApprovalWorkflowStep $step, int $amount): bool
    {
        $min = $step->min_amount;
        $max = $step->max_amount;

        if ($min !== null && $amount < (int) $min) {
            return false;
        }

        if ($max !== null && $amount > (int) $max) {
            return false;
        }

        return true;
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
            ->where('company_id', (int) $request->company_id)
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
            ->where('company_id', (int) $request->company_id)
            ->where('id', (int) $userId)
            ->where('is_active', true)
            ->first();

        return $user ? collect([$user]) : collect();
    }
}
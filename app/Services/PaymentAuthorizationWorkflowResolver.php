<?php

namespace App\Services;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;

class PaymentAuthorizationWorkflowResolver
{
    public function resolveDefaultWorkflow(int $companyId): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('applies_to', ApprovalWorkflow::APPLIES_TO_PAYMENT_AUTHORIZATION)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function hasActiveDefaultWorkflow(int $companyId): bool
    {
        return ApprovalWorkflow::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('applies_to', ApprovalWorkflow::APPLIES_TO_PAYMENT_AUTHORIZATION)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
    }

    /**
     * Resolve active payment-authorization steps and apply amount bounds.
     * This is the Phase 1.5 skeleton for execution-mode policy checks.
     *
     * @return \Illuminate\Support\Collection<int, ApprovalWorkflowStep>
     */
    public function resolveApplicableSteps(int $companyId, int $amount): \Illuminate\Support\Collection
    {
        $workflow = $this->resolveDefaultWorkflow($companyId);

        if (! $workflow) {
            return collect();
        }

        return ApprovalWorkflowStep::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflow->id)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->get()
            ->filter(function (ApprovalWorkflowStep $step) use ($amount): bool {
                if ($step->min_amount !== null && $amount < (int) $step->min_amount) {
                    return false;
                }

                if ($step->max_amount !== null && $amount > (int) $step->max_amount) {
                    return false;
                }

                return true;
            })
            ->values();
    }
}


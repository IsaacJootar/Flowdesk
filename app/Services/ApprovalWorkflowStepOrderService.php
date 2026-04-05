<?php

namespace App\Services;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;

/**
 * Service for managing the order and normalization of approval workflow steps.
 * Handles step ordering, key generation, and cleanup of deleted steps.
 */
class ApprovalWorkflowStepOrderService
{
    /**
     * Normalize the step order and keys for a specific workflow.
     * Ensures unique step keys and sequential ordering.
     */
    public function normalizeWorkflow(int $companyId, int $workflowId): void
    {
        $this->purgeDeletedSteps($companyId, $workflowId);

        $steps = ApprovalWorkflowStep::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflowId)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        $usedKeys = [];
        $position = 1;

        foreach ($steps as $step) {
            // Generate or use existing step key
            $key = $step->step_key ?: $this->defaultStepKeyForSource(
                source: (string) $step->actor_type,
                value: $step->actor_value ? (string) $step->actor_value : null
            );

            // Ensure uniqueness by appending position if duplicate
            if (in_array($key, $usedKeys, true)) {
                $key = $key.'_'.$position;
            }

            // Update step with new order and key
            $step->forceFill([
                'step_order' => $position,
                'step_key' => $key,
            ])->save();

            $usedKeys[] = $key;
            $position++;
        }
    }

    /**
     * Normalize all request workflows for a company.
     */
    public function normalizeCompanyRequestWorkflows(int $companyId): void
    {
        $this->normalizeCompanyWorkflowsByAppliesTo($companyId, ApprovalWorkflow::APPLIES_TO_REQUEST);
    }

    /**
     * Normalize all workflows of a specific type for a company.
     */
    public function normalizeCompanyWorkflowsByAppliesTo(int $companyId, string $appliesTo): void
    {
        $workflowIds = ApprovalWorkflow::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('applies_to', $appliesTo)
            ->pluck('id');

        foreach ($workflowIds as $workflowId) {
            $this->normalizeWorkflow($companyId, (int) $workflowId);
        }
    }

    /**
     * Get the next step order number for a workflow.
     */
    public function nextStepOrder(int $companyId, int $workflowId): int
    {
        $this->purgeDeletedSteps($companyId, $workflowId);

        return (int) ApprovalWorkflowStep::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflowId)
            ->where('is_active', true)
            ->count() + 1;
    }

    /**
     * Permanently delete soft-deleted steps for a workflow.
     */
    private function purgeDeletedSteps(int $companyId, int $workflowId): void
    {
        // withoutGlobalScopes keeps this safe for both tenant-owner and platform-operator contexts.
        ApprovalWorkflowStep::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflowId)
            ->whereNotNull('deleted_at')
            ->forceDelete();
    }

    /**
     * Generate a default step key based on the actor source.
     */
    private function defaultStepKeyForSource(string $source, ?string $value): string
    {
        return match ($source) {
            'reports_to' => 'direct_manager_review',
            'department_manager' => 'department_head_review',
            'role' => 'role_'.strtolower((string) $value).'_review',
            'user' => 'specific_user_'.(string) $value.'_review',
            default => 'approval_step_review',
        };
    }
}

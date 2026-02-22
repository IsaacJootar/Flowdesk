<?php

namespace App\Services;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;

class ApprovalWorkflowStepOrderService
{
    public function normalizeWorkflow(int $companyId, int $workflowId): void
    {
        $this->purgeDeletedSteps($companyId, $workflowId);

        $steps = ApprovalWorkflowStep::query()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflowId)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        $usedKeys = [];
        $position = 1;

        foreach ($steps as $step) {
            $key = $step->step_key ?: $this->defaultStepKeyForSource(
                source: (string) $step->actor_type,
                value: $step->actor_value ? (string) $step->actor_value : null
            );

            if (in_array($key, $usedKeys, true)) {
                $key = $key.'_'.$position;
            }

            $step->forceFill([
                'step_order' => $position,
                'step_key' => $key,
            ])->save();

            $usedKeys[] = $key;
            $position++;
        }
    }

    public function normalizeCompanyRequestWorkflows(int $companyId): void
    {
        $workflowIds = ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->where('applies_to', 'request')
            ->pluck('id');

        foreach ($workflowIds as $workflowId) {
            $this->normalizeWorkflow($companyId, (int) $workflowId);
        }
    }

    public function nextStepOrder(int $companyId, int $workflowId): int
    {
        $this->purgeDeletedSteps($companyId, $workflowId);

        return (int) ApprovalWorkflowStep::query()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflowId)
            ->where('is_active', true)
            ->count() + 1;
    }

    private function purgeDeletedSteps(int $companyId, int $workflowId): void
    {
        ApprovalWorkflowStep::withTrashed()
            ->where('company_id', $companyId)
            ->where('workflow_id', $workflowId)
            ->whereNotNull('deleted_at')
            ->forceDelete();
    }

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


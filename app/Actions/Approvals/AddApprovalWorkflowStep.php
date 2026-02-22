<?php

namespace App\Actions\Approvals;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ApprovalWorkflowStepOrderService;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddApprovalWorkflowStep
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ApprovalWorkflowStepOrderService $approvalWorkflowStepOrderService
    ) {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, ApprovalWorkflow $workflow, array $input): ApprovalWorkflowStep
    {
        $this->ensureOwner($actor);

        if ((int) $workflow->company_id !== (int) $actor->company_id) {
            throw new AuthorizationException('Cross-company workflow modification is not allowed.');
        }
        $this->approvalWorkflowStepOrderService->normalizeWorkflow((int) $actor->company_id, (int) $workflow->id);

        $validated = Validator::make($input, [
            'step_order' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('approval_workflow_steps', 'step_order')
                    ->where(fn ($query) => $query
                        ->where('workflow_id', $workflow->id)),
            ],
            'step_key' => ['nullable', 'string', 'max:80'],
            'actor_type' => ['required', Rule::in(['reports_to', 'department_manager', 'role', 'user'])],
            'actor_value' => ['nullable', 'string', 'max:100'],
            'min_amount' => ['nullable', 'integer', 'min:0'],
            'max_amount' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        $this->validateActorValue($workflow, $validated['actor_type'], $validated['actor_value'] ?? null);

        if (
            isset($validated['min_amount'], $validated['max_amount'])
            && (int) $validated['min_amount'] > (int) $validated['max_amount']
        ) {
            throw ValidationException::withMessages([
                'max_amount' => 'Max amount must be greater than or equal to min amount.',
            ]);
        }

        $nextStepOrder = $validated['step_order']
            ?? $this->approvalWorkflowStepOrderService->nextStepOrder((int) $actor->company_id, (int) $workflow->id);

        $step = ApprovalWorkflowStep::query()->create([
            'company_id' => (int) $actor->company_id,
            'workflow_id' => $workflow->id,
            'step_order' => (int) $nextStepOrder,
            'step_key' => $validated['step_key'] ? trim((string) $validated['step_key']) : null,
            'actor_type' => $validated['actor_type'],
            'actor_value' => $validated['actor_value'] ? trim((string) $validated['actor_value']) : null,
            'min_amount' => array_key_exists('min_amount', $validated) ? $validated['min_amount'] : null,
            'max_amount' => array_key_exists('max_amount', $validated) ? $validated['max_amount'] : null,
            'requires_all' => false,
            'is_active' => true,
        ]);

        $this->activityLogger->log(
            action: 'approval.workflow.step.created',
            entityType: ApprovalWorkflowStep::class,
            entityId: $step->id,
            metadata: [
                'workflow_id' => $workflow->id,
                'step_order' => $step->step_order,
                'actor_type' => $step->actor_type,
                'actor_value' => $step->actor_value,
            ],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        $this->approvalWorkflowStepOrderService->normalizeWorkflow((int) $actor->company_id, (int) $workflow->id);

        return $step;
    }

    /**
     * @throws ValidationException
     */
    private function validateActorValue(ApprovalWorkflow $workflow, string $actorType, ?string $actorValue): void
    {
        if (in_array($actorType, ['reports_to', 'department_manager'], true)) {
            return;
        }

        if ($actorType === 'role') {
            if (! $actorValue || ! in_array($actorValue, UserRole::values(), true)) {
                throw ValidationException::withMessages([
                    'actor_value' => 'Select a valid system role for role-based step.',
                ]);
            }

            return;
        }

        if ($actorType === 'user') {
            if (! $actorValue || ! is_numeric($actorValue)) {
                throw ValidationException::withMessages([
                    'actor_value' => 'Select a valid user for user-based step.',
                ]);
            }

            $exists = User::query()
                ->where('company_id', $workflow->company_id)
                ->where('id', (int) $actorValue)
                ->whereNull('deleted_at')
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'actor_value' => 'Selected user does not belong to this company.',
                ]);
            }
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOwner(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Owner)) {
            throw new AuthorizationException('Only owner can manage approval workflows.');
        }
    }
}

<?php

namespace App\Actions\Approvals;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteApprovalWorkflow
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, ApprovalWorkflow $workflow): void
    {
        $this->ensureOwner($actor);

        if ((int) $actor->company_id !== (int) $workflow->company_id) {
            throw new AuthorizationException('Cross-company workflow deletion is not allowed.');
        }

        $hasLinkedRequests = SpendRequest::query()
            ->where('company_id', $workflow->company_id)
            ->where('workflow_id', $workflow->id)
            ->exists();

        if ($hasLinkedRequests) {
            throw ValidationException::withMessages([
                'workflow' => 'Workflow cannot be deleted because requests are already linked to it.',
            ]);
        }

        $activeWorkflowCount = ApprovalWorkflow::query()
            ->where('company_id', $workflow->company_id)
            ->where('applies_to', $workflow->applies_to)
            ->where('is_active', true)
            ->count();

        if ($activeWorkflowCount <= 1) {
            throw ValidationException::withMessages([
                'workflow' => 'At least one active workflow must remain.',
            ]);
        }

        DB::transaction(function () use ($actor, $workflow): void {
            if ($workflow->is_default) {
                $replacement = ApprovalWorkflow::query()
                    ->where('company_id', $workflow->company_id)
                    ->where('applies_to', $workflow->applies_to)
                    ->where('is_active', true)
                    ->where('id', '!=', $workflow->id)
                    ->orderBy('id')
                    ->first();

                if ($replacement) {
                    $replacement->forceFill(['is_default' => true])->save();
                }
            }

            ApprovalWorkflowStep::query()
                ->where('company_id', $workflow->company_id)
                ->where('workflow_id', $workflow->id)
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                ]);

            $workflow->forceFill([
                'is_default' => false,
                'is_active' => false,
            ])->save();
            $workflow->delete();

            $this->activityLogger->log(
                action: 'approval.workflow.deleted',
                entityType: ApprovalWorkflow::class,
                entityId: $workflow->id,
                metadata: [
                    'name' => $workflow->name,
                    'code' => $workflow->code,
                ],
                companyId: (int) $actor->company_id,
                userId: $actor->id,
            );
        });
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


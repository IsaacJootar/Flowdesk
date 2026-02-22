<?php

namespace App\Actions\Approvals;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SetApprovalWorkflowDefault
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, ApprovalWorkflow $workflow): ApprovalWorkflow
    {
        $this->ensureOwner($actor);

        if ((int) $actor->company_id !== (int) $workflow->company_id) {
            throw new AuthorizationException('Cross-company workflow update is not allowed.');
        }

        DB::transaction(function () use ($workflow): void {
            ApprovalWorkflow::query()
                ->where('company_id', $workflow->company_id)
                ->where('applies_to', $workflow->applies_to)
                ->update(['is_default' => false]);

            $workflow->forceFill([
                'is_default' => true,
                'is_active' => true,
            ])->save();
        });

        $this->activityLogger->log(
            action: 'approval.workflow.default.changed',
            entityType: ApprovalWorkflow::class,
            entityId: $workflow->id,
            metadata: [
                'name' => $workflow->name,
                'applies_to' => $workflow->applies_to,
            ],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        return $workflow;
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


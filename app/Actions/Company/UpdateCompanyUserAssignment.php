<?php

namespace App\Actions\Company;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TenantSeatGovernanceService;
use App\Services\TenantUsageSnapshotService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCompanyUserAssignment
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly TenantSeatGovernanceService $tenantSeatGovernanceService,
        private readonly TenantUsageSnapshotService $tenantUsageSnapshotService
    )
    {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, User $subject, array $input): User
    {
        $this->ensureOwner($actor);

        if ((int) $actor->company_id !== (int) $subject->company_id) {
            throw new AuthorizationException('Cross-company user update is not allowed.');
        }

        $validated = Validator::make($input, [
            'role' => ['required', Rule::in(UserRole::values())],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'reports_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $reportsTo = $validated['reports_to_user_id'] ? (int) $validated['reports_to_user_id'] : null;
        $nextIsActive = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : (bool) $subject->is_active;

        if ($reportsTo && $reportsTo === (int) $subject->id) {
            throw ValidationException::withMessages([
                'reports_to_user_id' => 'A user cannot report to themselves.',
            ]);
        }

        // Activating an inactive user consumes a seat and must respect the cap.
        if ($nextIsActive && ! (bool) $subject->is_active) {
            $this->tenantSeatGovernanceService->assertCanAddActiveUser((int) $actor->company_id);
        }

        $subject->forceFill([
            'role' => $validated['role'],
            'department_id' => (int) $validated['department_id'],
            'reports_to_user_id' => $reportsTo,
            'is_active' => $nextIsActive,
        ])->save();

        $this->activityLogger->log(
            action: 'identity.user.assignment.updated',
            entityType: User::class,
            entityId: $subject->id,
            metadata: [
                'role' => $subject->role,
                'department_id' => $subject->department_id,
                'reports_to_user_id' => $subject->reports_to_user_id,
                'is_active' => $subject->is_active,
            ],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        $this->tenantUsageSnapshotService->capture((int) $actor->company_id, $actor);

        return $subject;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOwner(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Owner)) {
            throw new AuthorizationException('Only admin (owner) can manage team assignments.');
        }
    }
}


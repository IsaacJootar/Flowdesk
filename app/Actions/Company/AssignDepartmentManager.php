<?php

namespace App\Actions\Company;

use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignDepartmentManager
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, Department $department, array $input): Department
    {
        $this->ensureOwner($actor);

        if ((int) $actor->company_id !== (int) $department->company_id) {
            throw new AuthorizationException('Cross-company department management is not allowed.');
        }

        $validated = Validator::make($input, [
            'manager_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
        ])->validate();

        $department->forceFill([
            'manager_user_id' => $validated['manager_user_id'] ? (int) $validated['manager_user_id'] : null,
        ])->save();

        $this->activityLogger->log(
            action: 'department.manager.assigned',
            entityType: Department::class,
            entityId: $department->id,
            metadata: ['manager_user_id' => $department->manager_user_id],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        return $department;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOwner(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Owner)) {
            throw new AuthorizationException('Only owner can manage organization structure.');
        }
    }
}


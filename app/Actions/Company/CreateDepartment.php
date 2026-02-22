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

class CreateDepartment
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, array $input): Department
    {
        $this->ensureOwner($actor);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:32'],
            'manager_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
        ])->validate();

        $department = Department::query()->create([
            'company_id' => (int) $actor->company_id,
            'name' => trim($validated['name']),
            'code' => $validated['code'] ? strtoupper(trim((string) $validated['code'])) : null,
            'manager_user_id' => $validated['manager_user_id'] ? (int) $validated['manager_user_id'] : null,
            'is_active' => true,
        ]);

        $this->activityLogger->log(
            action: 'department.created',
            entityType: Department::class,
            entityId: $department->id,
            metadata: [
                'name' => $department->name,
                'code' => $department->code,
                'manager_user_id' => $department->manager_user_id,
            ],
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


<?php

namespace App\Actions\Budgets;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CloseDepartmentBudget
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, DepartmentBudget $budget): DepartmentBudget
    {
        Gate::forUser($user)->authorize('update', $budget);

        if ($budget->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => 'Budget is already closed.',
            ]);
        }

        $budget->forceFill(['status' => 'closed'])->save();

        $this->activityLogger->log(
            action: 'budget.closed',
            entityType: DepartmentBudget::class,
            entityId: $budget->id,
            metadata: [
                'department_id' => $budget->department_id,
                'period_start' => optional($budget->period_start)?->toDateString(),
                'period_end' => optional($budget->period_end)?->toDateString(),
                'allocated_amount' => $budget->allocated_amount,
            ],
            companyId: $budget->company_id,
            userId: $user->id,
        );

        return $budget;
    }
}


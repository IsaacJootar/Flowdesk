<?php

namespace App\Actions\Budgets;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateDepartmentBudget
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): DepartmentBudget
    {
        Gate::forUser($user)->authorize('create', DepartmentBudget::class);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'User must belong to a company before creating budgets.',
            ]);
        }

        $validated = Validator::make($input, $this->rules((int) $user->company_id))
            ->after(function ($validator) use ($user, $input): void {
                $departmentId = (int) ($input['department_id'] ?? 0);
                $start = (string) ($input['period_start'] ?? '');
                $end = (string) ($input['period_end'] ?? '');

                if ($departmentId <= 0 || $start === '' || $end === '') {
                    return;
                }

                $hasOverlap = DepartmentBudget::query()
                    ->where('company_id', (int) $user->company_id)
                    ->where('department_id', $departmentId)
                    ->where('status', 'active')
                    ->whereDate('period_start', '<=', $end)
                    ->whereDate('period_end', '>=', $start)
                    ->exists();

                if ($hasOverlap) {
                    $validator->errors()->add('period_start', 'An active budget already covers this period for the selected department.');
                }
            })
            ->validate();

        $budget = DB::transaction(function () use ($user, $validated): DepartmentBudget {
            $allocatedAmount = (int) $validated['allocated_amount'];

            return DepartmentBudget::query()->create([
                'company_id' => $user->company_id,
                'department_id' => (int) $validated['department_id'],
                'period_type' => (string) $validated['period_type'],
                'period_start' => (string) $validated['period_start'],
                'period_end' => (string) $validated['period_end'],
                'allocated_amount' => $allocatedAmount,
                'used_amount' => 0,
                'remaining_amount' => $allocatedAmount,
                'status' => 'active',
                'created_by' => $user->id,
            ]);
        });

        $this->activityLogger->log(
            action: 'budget.created',
            entityType: DepartmentBudget::class,
            entityId: $budget->id,
            metadata: [
                'department_id' => $budget->department_id,
                'period_type' => $budget->period_type,
                'period_start' => optional($budget->period_start)?->toDateString(),
                'period_end' => optional($budget->period_end)?->toDateString(),
                'allocated_amount' => $budget->allocated_amount,
                'status' => $budget->status,
            ],
            companyId: $budget->company_id,
            userId: $user->id,
        );

        return $budget;
    }

    private function rules(int $companyId): array
    {
        return [
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'period_type' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'allocated_amount' => ['required', 'integer', 'min:1'],
        ];
    }
}


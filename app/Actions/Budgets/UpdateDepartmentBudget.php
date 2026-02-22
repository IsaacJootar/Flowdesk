<?php

namespace App\Actions\Budgets;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentBudget
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, DepartmentBudget $budget, array $input): DepartmentBudget
    {
        Gate::forUser($user)->authorize('update', $budget);

        $validated = Validator::make($input, $this->rules((int) $budget->company_id))
            ->after(function ($validator) use ($budget, $input): void {
                $departmentId = (int) ($input['department_id'] ?? 0);
                $start = (string) ($input['period_start'] ?? '');
                $end = (string) ($input['period_end'] ?? '');

                if ($departmentId <= 0 || $start === '' || $end === '') {
                    return;
                }

                $hasOverlap = DepartmentBudget::query()
                    ->where('company_id', (int) $budget->company_id)
                    ->where('department_id', $departmentId)
                    ->where('status', 'active')
                    ->where('id', '!=', $budget->id)
                    ->whereDate('period_start', '<=', $end)
                    ->whereDate('period_end', '>=', $start)
                    ->exists();

                if ($hasOverlap) {
                    $validator->errors()->add('period_start', 'An active budget already covers this period for the selected department.');
                }
            })
            ->validate();

        $before = $budget->only([
            'department_id',
            'period_type',
            'period_start',
            'period_end',
            'allocated_amount',
            'status',
        ]);

        $budget->fill([
            'department_id' => (int) $validated['department_id'],
            'period_type' => (string) $validated['period_type'],
            'period_start' => (string) $validated['period_start'],
            'period_end' => (string) $validated['period_end'],
            'allocated_amount' => (int) $validated['allocated_amount'],
        ]);

        if (! $budget->isDirty()) {
            throw ValidationException::withMessages([
                'no_changes' => 'No changes made. Update at least one field before saving.',
            ]);
        }

        $budget->save();

        $after = $budget->only(array_keys($before));

        $this->activityLogger->log(
            action: 'budget.updated',
            entityType: DepartmentBudget::class,
            entityId: $budget->id,
            metadata: [
                'before' => $before,
                'after' => $after,
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

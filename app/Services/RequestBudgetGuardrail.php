<?php

namespace App\Services;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Support\Carbon;

class RequestBudgetGuardrail
{
    /**
     * @return array{
     *   has_budget: bool,
     *   budget_id: int|null,
     *   allocated_amount: int,
     *   spent_amount: int,
     *   projected_amount: int,
     *   remaining_amount: int,
     *   over_amount: int,
     *   is_exceeded: bool,
     *   warning_level: string|null,
     *   effective_date: string
     * }
     */
    public function evaluate(
        int $companyId,
        int $departmentId,
        int $incomingAmount,
        ?string $effectiveDate = null
    ): array {
        $date = $this->resolveEffectiveDate($effectiveDate);

        if ($incomingAmount <= 0) {
            return [
                'has_budget' => false,
                'budget_id' => null,
                'allocated_amount' => 0,
                'spent_amount' => 0,
                'projected_amount' => 0,
                'remaining_amount' => 0,
                'over_amount' => 0,
                'is_exceeded' => false,
                'warning_level' => null,
                'effective_date' => $date,
            ];
        }

        /** @var DepartmentBudget|null $budget */
        $budget = DepartmentBudget::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->where('status', 'active')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->orderByDesc('period_start')
            ->first();

        if (! $budget) {
            return [
                'has_budget' => false,
                'budget_id' => null,
                'allocated_amount' => 0,
                'spent_amount' => 0,
                'projected_amount' => $incomingAmount,
                'remaining_amount' => 0,
                'over_amount' => 0,
                'is_exceeded' => false,
                'warning_level' => null,
                'effective_date' => $date,
            ];
        }

        // Guardrail compares projected spend against active budget window using posted expenses only.
        $spentAmount = (int) Expense::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->where('status', 'posted')
            ->whereDate('expense_date', '>=', $budget->period_start?->toDateString())
            ->whereDate('expense_date', '<=', $budget->period_end?->toDateString())
            ->sum('amount');

        $allocatedAmount = (int) $budget->allocated_amount;
        $projectedAmount = $spentAmount + $incomingAmount;
        $remainingAmount = $allocatedAmount - $spentAmount;
        $overAmount = max(0, $projectedAmount - $allocatedAmount);
        $isExceeded = $projectedAmount > $allocatedAmount;

        $warningLevel = null;
        $ratio = $allocatedAmount > 0 ? ($projectedAmount / $allocatedAmount) : 0.0;
        if (! $isExceeded && $ratio >= 1.0) {
            $warningLevel = 'critical';
        } elseif (! $isExceeded && $ratio >= 0.9) {
            $warningLevel = 'high';
        } elseif (! $isExceeded && $ratio >= 0.75) {
            $warningLevel = 'medium';
        }

        return [
            'has_budget' => true,
            'budget_id' => (int) $budget->id,
            'allocated_amount' => $allocatedAmount,
            'spent_amount' => $spentAmount,
            'projected_amount' => $projectedAmount,
            'remaining_amount' => $remainingAmount,
            'over_amount' => $overAmount,
            'is_exceeded' => $isExceeded,
            'warning_level' => $warningLevel,
            'effective_date' => $date,
        ];
    }

    private function resolveEffectiveDate(?string $effectiveDate): string
    {
        if ($effectiveDate && trim($effectiveDate) !== '') {
            return Carbon::parse($effectiveDate)->toDateString();
        }

        return now()->toDateString();
    }
}

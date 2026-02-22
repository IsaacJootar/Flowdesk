<?php

namespace App\Services;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExpenseBudgetGuardrail
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
     *   is_blocked: bool,
     *   warning_level: string|null
     * }
     */
    public function evaluate(
        int $companyId,
        int $departmentId,
        string $expenseDate,
        int $incomingAmount,
        ?Expense $editingExpense = null
    ): array {
        $date = Carbon::parse($expenseDate)->toDateString();

        /** @var DepartmentBudget|null $budget */
        $budget = DepartmentBudget::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->where('status', 'active')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->orderByDesc('period_start')
            ->first();

        if (!$budget) {
            return [
                'has_budget' => false,
                'budget_id' => null,
                'allocated_amount' => 0,
                'spent_amount' => 0,
                'projected_amount' => $incomingAmount,
                'remaining_amount' => 0,
                'over_amount' => 0,
                'is_blocked' => false,
                'warning_level' => null,
            ];
        }

        $spentQuery = Expense::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->where('status', 'posted')
            ->whereDate('expense_date', '>=', $budget->period_start?->toDateString())
            ->whereDate('expense_date', '<=', $budget->period_end?->toDateString());

        if ($editingExpense) {
            $spentQuery->where('id', '!=', $editingExpense->id);
        }

        $spentAmount = (int) $spentQuery->sum('amount');
        $allocatedAmount = (int) $budget->allocated_amount;
        $projectedAmount = $spentAmount + $incomingAmount;
        $remainingAmount = $allocatedAmount - $spentAmount;
        $overAmount = max(0, $projectedAmount - $allocatedAmount);
        $isBlocked = $projectedAmount > $allocatedAmount;

        $utilizationRatio = $allocatedAmount > 0 ? ($projectedAmount / $allocatedAmount) : 0.0;
        $warningLevel = null;
        if (! $isBlocked && $utilizationRatio >= 1.0) {
            $warningLevel = 'critical';
        } elseif (! $isBlocked && $utilizationRatio >= 0.9) {
            $warningLevel = 'high';
        } elseif (! $isBlocked && $utilizationRatio >= 0.75) {
            $warningLevel = 'medium';
        }

        return [
            'has_budget' => true,
            'budget_id' => $budget->id,
            'allocated_amount' => $allocatedAmount,
            'spent_amount' => $spentAmount,
            'projected_amount' => $projectedAmount,
            'remaining_amount' => $remainingAmount,
            'over_amount' => $overAmount,
            'is_blocked' => $isBlocked,
            'warning_level' => $warningLevel,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function enforceOrFail(
        int $companyId,
        int $departmentId,
        string $expenseDate,
        int $incomingAmount,
        ?Expense $editingExpense = null
    ): array {
        $result = $this->evaluate(
            companyId: $companyId,
            departmentId: $departmentId,
            expenseDate: $expenseDate,
            incomingAmount: $incomingAmount,
            editingExpense: $editingExpense
        );

        if ($result['has_budget'] && $result['is_blocked']) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Budget limit exceeded by NGN %s for this department period.',
                    number_format($result['over_amount'])
                ),
            ]);
        }

        return $result;
    }
}


<?php

namespace App\Services;

use App\Domains\Expenses\Models\Expense;

class ExpenseDuplicateDetector
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   risk: 'none'|'soft'|'hard',
     *   matches: array<int, array{
     *     id: int,
     *     expense_code: string,
     *     title: string,
     *     amount: int,
     *     expense_date: string|null
     *   }>
     * }
     */
    public function analyze(int $companyId, array $input, ?int $excludeExpenseId = null): array
    {
        $amount = (int) ($input['amount'] ?? 0);
        $expenseDate = (string) ($input['expense_date'] ?? '');
        $title = (string) ($input['title'] ?? '');
        $vendorId = $input['vendor_id'] ?? null;

        if ($amount <= 0 || $expenseDate === '') {
            return ['risk' => 'none', 'matches' => []];
        }

        $query = Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->where('amount', $amount)
            ->whereDate('expense_date', $expenseDate)
            ->when(
                $vendorId === null || $vendorId === '',
                fn ($builder) => $builder->whereNull('vendor_id'),
                fn ($builder) => $builder->where('vendor_id', (int) $vendorId)
            )
            ->when($excludeExpenseId, fn ($builder) => $builder->where('id', '!=', $excludeExpenseId))
            ->orderByDesc('id')
            ->limit(5);

        $matches = $query->get(['id', 'expense_code', 'title', 'amount', 'expense_date'])
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_code' => (string) $expense->expense_code,
                'title' => (string) $expense->title,
                'amount' => (int) $expense->amount,
                'expense_date' => $expense->expense_date?->toDateString(),
            ])
            ->values()
            ->all();

        if (empty($matches)) {
            return ['risk' => 'none', 'matches' => []];
        }

        $currentTitle = $this->normalizeTitle($title);
        $hasExactTitleMatch = false;

        foreach ($matches as $match) {
            if ($this->normalizeTitle((string) $match['title']) === $currentTitle && $currentTitle !== '') {
                $hasExactTitleMatch = true;
                break;
            }
        }

        return [
            'risk' => $hasExactTitleMatch ? 'hard' : 'soft',
            'matches' => $matches,
        ];
    }

    private function normalizeTitle(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}


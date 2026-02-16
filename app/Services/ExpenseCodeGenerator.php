<?php

namespace App\Services;

use App\Domains\Expenses\Models\Expense;

class ExpenseCodeGenerator
{
    public function generateForCompany(int $companyId): string
    {
        $latestCode = Expense::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('expense_code', 'like', 'FD-EXP-%')
            ->latest('id')
            ->value('expense_code');

        $nextNumber = 1;

        if (is_string($latestCode) && str_starts_with($latestCode, 'FD-EXP-')) {
            $sequence = (int) substr($latestCode, 7);
            $nextNumber = $sequence + 1;
        }

        return 'FD-EXP-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

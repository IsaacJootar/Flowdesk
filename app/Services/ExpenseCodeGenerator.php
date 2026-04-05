<?php

namespace App\Services;

use App\Domains\Expenses\Models\Expense;

/**
 * Service for generating unique expense codes for companies.
 * Generates codes in the format FD-EXP-XXXXXX where XXXXXX is a sequential number.
 */
class ExpenseCodeGenerator
{
    /**
     * Generate a new expense code for the given company.
     * The code is company-scoped and sequential.
     */
    public function generateForCompany(int $companyId): string
    {
        // Sequence is company-scoped; bypass global scope to avoid tenant context leakage.
        $latestCode = Expense::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('expense_code', 'like', 'FD-EXP-%')
            ->latest('id')
            ->value('expense_code');

        // Determine the next number in the sequence
        $nextNumber = 1;

        if (is_string($latestCode) && str_starts_with($latestCode, 'FD-EXP-')) {
            $sequence = (int) substr($latestCode, 7);
            $nextNumber = $sequence + 1;
        }

        // Format the code with leading zeros
        return 'FD-EXP-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

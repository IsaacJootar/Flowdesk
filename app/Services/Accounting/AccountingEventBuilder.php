<?php

namespace App\Services\Accounting;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\AccountingCategory;

class AccountingEventBuilder
{
    /**
     * @return array<string,mixed>
     */
    public function fromExpense(Expense $expense): array
    {
        $expense->loadMissing('company:id,currency_code', 'department:id,name', 'vendor:id,name', 'request:id,request_code,currency');

        return [
            'company_id' => (int) $expense->company_id,
            'source_type' => 'expense',
            'source_id' => (int) $expense->id,
            'event_type' => 'expense_posted',
            'category_key' => AccountingCategory::normalize($expense->accounting_category_key),
            'amount' => (int) $expense->amount,
            'currency_code' => $this->currencyForExpense($expense),
            'event_date' => $expense->expense_date?->toDateString() ?: now()->toDateString(),
            'description' => trim((string) ($expense->expense_code.' - '.$expense->title)),
            'metadata' => [
                'expense_code' => (string) $expense->expense_code,
                'expense_title' => (string) $expense->title,
                'request_id' => $expense->request_id ? (int) $expense->request_id : null,
                'request_code' => (string) ($expense->request?->request_code ?? ''),
                'department_id' => $expense->department_id ? (int) $expense->department_id : null,
                'department_name' => (string) ($expense->department?->name ?? ''),
                'vendor_id' => $expense->vendor_id ? (int) $expense->vendor_id : null,
                'vendor_name' => (string) ($expense->vendor?->name ?? ''),
                'is_direct' => (bool) $expense->is_direct,
                'payment_method' => (string) ($expense->payment_method ?? ''),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function fromExpenseVoid(Expense $expense): array
    {
        $payload = $this->fromExpense($expense);
        $payload['event_type'] = 'expense_voided';
        $payload['amount'] = -1 * abs((int) $expense->amount);
        $payload['event_date'] = $expense->voided_at?->toDateString() ?: now()->toDateString();
        $payload['description'] = trim((string) ($expense->expense_code.' - Reversal for voided expense'));
        $payload['metadata'] = array_merge((array) ($payload['metadata'] ?? []), [
            'voided_by' => $expense->voided_by ? (int) $expense->voided_by : null,
            'voided_at' => $expense->voided_at?->toIso8601String(),
            'void_reason' => (string) ($expense->void_reason ?? ''),
            'reversal' => true,
        ]);

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    public function fromPayoutAttempt(RequestPayoutExecutionAttempt $attempt): array
    {
        $attempt->loadMissing('request.company:id,currency_code', 'request.department:id,name', 'request.vendor:id,name');
        $request = $attempt->request;

        return [
            'company_id' => (int) $attempt->company_id,
            'source_type' => 'payout',
            'source_id' => (int) $attempt->id,
            'event_type' => 'payout_completed',
            'category_key' => $request instanceof SpendRequest
                ? AccountingCategory::normalize($request->accounting_category_key)
                : null,
            'amount' => (int) round((float) $attempt->amount),
            'currency_code' => strtoupper((string) ($attempt->currency_code ?: $request?->currency ?: $request?->company?->currency_code ?: 'NGN')),
            'event_date' => $attempt->settled_at?->toDateString() ?: now()->toDateString(),
            'description' => trim((string) ('Payout settled for '.($request?->request_code ?: 'request #'.$attempt->request_id))),
            'metadata' => [
                'request_id' => (int) $attempt->request_id,
                'request_code' => (string) ($request?->request_code ?? ''),
                'department_id' => $request?->department_id ? (int) $request->department_id : null,
                'department_name' => (string) ($request?->department?->name ?? ''),
                'vendor_id' => $request?->vendor_id ? (int) $request->vendor_id : null,
                'vendor_name' => (string) ($request?->vendor?->name ?? ''),
                'payout_attempt_id' => (int) $attempt->id,
                'provider_key' => (string) $attempt->provider_key,
                'execution_channel' => (string) $attempt->execution_channel,
                'provider_reference' => (string) ($attempt->provider_reference ?? ''),
            ],
        ];
    }

    private function currencyForExpense(Expense $expense): string
    {
        return strtoupper((string) ($expense->request?->currency ?: $expense->company?->currency_code ?: 'NGN'));
    }
}

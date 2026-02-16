<?php

namespace App\Services;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\Vendor;
use Illuminate\Support\Collection;

class VendorPaymentInsights
{
    /**
     * @return array{
     *     total_paid: int,
     *     payments_count: int,
     *     last_payment_date: ?string,
     *     recent_payments: Collection<int, Expense>
     * }
     */
    public function forVendor(Vendor $vendor): array
    {
        $stats = Expense::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_paid')
            ->selectRaw('COUNT(*) AS payments_count')
            ->selectRaw('MAX(expense_date) AS last_payment_date')
            ->first();

        $recentPayments = Expense::query()
            ->with(['department:id,name', 'creator:id,name'])
            ->where('vendor_id', $vendor->id)
            ->latest('expense_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'total_paid' => (int) ($stats?->total_paid ?? 0),
            'payments_count' => (int) ($stats?->payments_count ?? 0),
            'last_payment_date' => $stats?->last_payment_date,
            'recent_payments' => $recentPayments,
        ];
    }
}

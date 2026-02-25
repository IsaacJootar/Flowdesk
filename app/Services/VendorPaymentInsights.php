<?php

namespace App\Services;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use Illuminate\Support\Collection;

class VendorPaymentInsights
{
    /**
     * @return array{
     *     total_paid: int,
     *     payments_count: int,
     *     last_payment_date: ?string,
     *     recent_payments: Collection<int, Expense>,
     *     total_invoiced: int,
     *     total_invoice_paid: int,
     *     total_outstanding: int,
     *     invoices_count: int,
     *     unpaid_invoices_count: int,
     *     part_paid_invoices_count: int,
     *     paid_invoices_count: int,
     *     invoices: Collection<int, VendorInvoice>,
     *     statement_timeline: Collection<int, array<string, mixed>>
     * }
     */
    public function forVendor(Vendor $vendor): array
    {
        // Expense stats keep historical compatibility with current vendor panel cards.
        $expenseStats = Expense::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_paid')
            ->selectRaw('COUNT(*) AS payments_count')
            ->selectRaw('MAX(expense_date) AS last_payment_date')
            ->first();

        $recentPayments = Expense::query()
            ->with(['department:id,name', 'creator:id,name'])
            ->where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->latest('expense_date')
            ->latest('id')
            ->limit(10)
            ->get();

        $invoiceStats = VendorInvoice::query()
            ->where('vendor_id', $vendor->id)
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_invoiced')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) AS total_invoice_paid')
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) AS total_outstanding')
            ->selectRaw('COUNT(*) AS invoices_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS unpaid_invoices_count', [VendorInvoice::STATUS_UNPAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS part_paid_invoices_count', [VendorInvoice::STATUS_PART_PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid_invoices_count', [VendorInvoice::STATUS_PAID])
            ->first();

        $invoices = VendorInvoice::query()
            ->with([
                'attachments' => fn ($query) => $query->latest('uploaded_at')->latest('id'),
                'payments' => fn ($query) => $query
                    ->with(['attachments' => fn ($attachmentQuery) => $attachmentQuery->latest('uploaded_at')->latest('id')])
                    ->latest('payment_date')
                    ->latest('id'),
            ])
            ->where('vendor_id', $vendor->id)
            ->latest('invoice_date')
            ->latest('id')
            ->limit(15)
            ->get();

        $statementTimeline = $this->statementTimeline($vendor);

        return [
            'total_paid' => (int) ($expenseStats?->total_paid ?? 0),
            'payments_count' => (int) ($expenseStats?->payments_count ?? 0),
            'last_payment_date' => $expenseStats?->last_payment_date,
            'recent_payments' => $recentPayments,
            'total_invoiced' => (int) ($invoiceStats?->total_invoiced ?? 0),
            'total_invoice_paid' => (int) ($invoiceStats?->total_invoice_paid ?? 0),
            'total_outstanding' => (int) ($invoiceStats?->total_outstanding ?? 0),
            'invoices_count' => (int) ($invoiceStats?->invoices_count ?? 0),
            'unpaid_invoices_count' => (int) ($invoiceStats?->unpaid_invoices_count ?? 0),
            'part_paid_invoices_count' => (int) ($invoiceStats?->part_paid_invoices_count ?? 0),
            'paid_invoices_count' => (int) ($invoiceStats?->paid_invoices_count ?? 0),
            'invoices' => $invoices,
            'statement_timeline' => $statementTimeline,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function statementTimeline(Vendor $vendor): Collection
    {
        $invoiceEvents = VendorInvoice::query()
            ->where('vendor_id', $vendor->id)
            ->latest('invoice_date')
            ->limit(30)
            ->get()
            ->map(fn (VendorInvoice $invoice): array => [
                'event_type' => 'invoice',
                'event_subtype' => (string) $invoice->status,
                'title' => 'Invoice '.$invoice->invoice_number,
                'amount' => (int) $invoice->total_amount,
                'happened_at' => optional($invoice->invoice_date)->toDateString(),
                'meta' => [
                    'invoice_id' => $invoice->id,
                    'paid_amount' => (int) $invoice->paid_amount,
                    'outstanding_amount' => (int) $invoice->outstanding_amount,
                ],
            ]);

        $paymentEvents = VendorInvoicePayment::query()
            ->with(['attachments' => fn ($query) => $query->latest('uploaded_at')->latest('id')])
            ->where('vendor_id', $vendor->id)
            ->latest('payment_date')
            ->limit(30)
            ->get()
            ->map(fn (VendorInvoicePayment $payment): array => [
                'event_type' => 'payment',
                'event_subtype' => (string) ($payment->payment_method ?: 'other'),
                'title' => 'Payment'.($payment->payment_reference ? ' ('.$payment->payment_reference.')' : ''),
                'amount' => (int) $payment->amount,
                'happened_at' => optional($payment->payment_date)->toDateString(),
                'meta' => [
                    'invoice_id' => $payment->vendor_invoice_id,
                    'payment_id' => $payment->id,
                    'attachments' => $payment->attachments
                        ->map(fn ($attachment): array => [
                            'id' => (int) $attachment->id,
                            'original_name' => (string) $attachment->original_name,
                            'mime_type' => (string) $attachment->mime_type,
                            'file_size' => (int) $attachment->file_size,
                            'uploaded_at' => optional($attachment->uploaded_at)->format('M d, Y H:i'),
                        ])
                        ->all(),
                ],
            ]);

        return $invoiceEvents
            ->concat($paymentEvents)
            ->sortByDesc(fn (array $event): string => (string) ($event['happened_at'] ?? ''))
            ->values()
            ->take(40);
    }
}

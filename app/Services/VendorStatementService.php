<?php

namespace App\Services;

use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use Illuminate\Support\Collection;

class VendorStatementService
{
    /**
     * @return array{
     *   rows: Collection<int, array<string, mixed>>,
     *   summary: array{invoice_total:int,payment_total:int,balance:int,currency:string},
     *   filters: array{from:?string,to:?string,invoice_status:string}
     * }
     */
    public function build(
        Vendor $vendor,
        ?string $from = null,
        ?string $to = null,
        string $invoiceStatus = 'all'
    ): array {
        $invoices = $this->invoiceQuery($vendor, $from, $to, $invoiceStatus)->get();
        $payments = $this->paymentQuery($vendor, $from, $to)->get();

        $rows = collect();
        foreach ($invoices as $invoice) {
            $rows->push([
                'date' => optional($invoice->invoice_date)->toDateString(),
                'date_label' => optional($invoice->invoice_date)->format('M d, Y'),
                'type' => 'invoice',
                'reference' => (string) $invoice->invoice_number,
                'description' => (string) ($invoice->description ?: 'Vendor invoice'),
                'status' => $this->displayStatus($invoice),
                'debit' => (int) $invoice->total_amount,
                'credit' => 0,
            ]);
        }

        foreach ($payments as $payment) {
            $rows->push([
                'date' => optional($payment->payment_date)->toDateString(),
                'date_label' => optional($payment->payment_date)->format('M d, Y'),
                'type' => 'payment',
                'reference' => (string) ($payment->payment_reference ?: ('PAY-'.$payment->id)),
                'description' => 'Invoice '.(string) ($payment->invoice?->invoice_number ?? '#'.$payment->vendor_invoice_id),
                'status' => 'posted',
                'debit' => 0,
                'credit' => (int) $payment->amount,
            ]);
        }

        $rows = $rows
            ->sortBy(function (array $row): string {
                return (string) ($row['date'] ?? '').'|'.(string) ($row['type'] ?? '');
            })
            ->values();

        $runningBalance = 0;
        $rows = $rows->map(function (array $row) use (&$runningBalance): array {
            $runningBalance += ((int) $row['debit'] - (int) $row['credit']);
            $row['balance'] = $runningBalance;

            return $row;
        });

        $currency = strtoupper((string) ($invoices->first()?->currency ?: 'NGN'));
        $invoiceTotal = (int) $invoices->sum('total_amount');
        $paymentTotal = (int) $payments->sum('amount');

        return [
            'rows' => $rows,
            'summary' => [
                'invoice_total' => $invoiceTotal,
                'payment_total' => $paymentTotal,
                'balance' => $invoiceTotal - $paymentTotal,
                'currency' => $currency,
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'invoice_status' => $invoiceStatus,
            ],
        ];
    }

    private function invoiceQuery(Vendor $vendor, ?string $from, ?string $to, string $invoiceStatus)
    {
        $query = VendorInvoice::query()
            ->where('vendor_id', (int) $vendor->id)
            ->orderBy('invoice_date')
            ->orderBy('id');

        if ($from) {
            $query->whereDate('invoice_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('invoice_date', '<=', $to);
        }

        if ($invoiceStatus === VendorInvoice::STATUS_OVERDUE) {
            $query
                ->where('status', '!=', VendorInvoice::STATUS_VOID)
                ->where('outstanding_amount', '>', 0)
                ->whereDate('due_date', '<', now()->toDateString());
        } elseif ($invoiceStatus !== 'all') {
            $query->where('status', $invoiceStatus);
        }

        return $query;
    }

    private function paymentQuery(Vendor $vendor, ?string $from, ?string $to)
    {
        $query = VendorInvoicePayment::query()
            ->with('invoice:id,invoice_number')
            ->where('vendor_id', (int) $vendor->id)
            ->orderBy('payment_date')
            ->orderBy('id');

        if ($from) {
            $query->whereDate('payment_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('payment_date', '<=', $to);
        }

        return $query;
    }

    private function displayStatus(VendorInvoice $invoice): string
    {
        if ((string) $invoice->status === VendorInvoice::STATUS_VOID) {
            return VendorInvoice::STATUS_VOID;
        }

        if ((int) $invoice->outstanding_amount <= 0) {
            return VendorInvoice::STATUS_PAID;
        }

        $dueDate = $invoice->due_date?->copy()->startOfDay();
        if ($dueDate && $dueDate->lt(now()->startOfDay())) {
            return VendorInvoice::STATUS_OVERDUE;
        }

        return (int) $invoice->paid_amount > 0
            ? VendorInvoice::STATUS_PART_PAID
            : VendorInvoice::STATUS_UNPAID;
    }
}

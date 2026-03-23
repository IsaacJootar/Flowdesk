<?php

namespace App\Http\Controllers;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\Vendor;
use App\Services\VendorStatementService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorStatementCsvExportController extends Controller
{
    public function __invoke(
        Request $request,
        Vendor $vendor,
        VendorStatementService $statementService
    ): StreamedResponse {
        Gate::authorize('exportStatements', $vendor);

        $validated = Validator::make([
            'from' => $this->cleanDate($request->query('from')),
            'to' => $this->cleanDate($request->query('to')),
            'invoice_status' => strtolower(trim((string) $request->query('invoice_status', 'all'))),
        ], [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'invoice_status' => ['required', Rule::in($this->allowedInvoiceStatuses())],
        ])->validate();

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $invoiceStatus = (string) ($validated['invoice_status'] ?? 'all');

        $statement = $statementService->build($vendor, $from, $to, $invoiceStatus);
        $rows = $statement['rows'];
        $summary = $statement['summary'];
        $fileName = 'vendor-statement-'.\Illuminate\Support\Str::slug((string) $vendor->name).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($vendor, $rows, $summary, $from, $to): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Vendor Statement']);
            fputcsv($handle, ['Vendor', (string) $vendor->name]);
            fputcsv($handle, ['Period', ($from ?: 'Start').' to '.($to ?: 'Today')]);
            fputcsv($handle, ['Currency', (string) $summary['currency']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Type', 'Reference', 'Description', 'Status', 'Debit', 'Credit', 'Running Balance']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) ($row['date'] ?? ''),
                    strtoupper((string) ($row['type'] ?? '')),
                    (string) ($row['reference'] ?? ''),
                    (string) ($row['description'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    str_replace(',', '', Money::formatPlain((int) ($row['debit'] ?? 0), 2)),
                    str_replace(',', '', Money::formatPlain((int) ($row['credit'] ?? 0), 2)),
                    str_replace(',', '', Money::formatPlain((int) ($row['balance'] ?? 0), 2)),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Invoice Total', str_replace(',', '', Money::formatPlain((int) $summary['invoice_total'], 2))]);
            fputcsv($handle, ['Payment Total', str_replace(',', '', Money::formatPlain((int) $summary['payment_total'], 2))]);
            fputcsv($handle, ['Closing Balance', str_replace(',', '', Money::formatPlain((int) $summary['balance'], 2))]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function cleanDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function allowedInvoiceStatuses(): array
    {
        return array_values(array_unique(array_merge(
            ['all'],
            VendorInvoice::DISPLAY_STATUSES
        )));
    }
}

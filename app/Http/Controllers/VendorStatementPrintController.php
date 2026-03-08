<?php

namespace App\Http\Controllers;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\Vendor;
use App\Services\VendorStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorStatementPrintController extends Controller
{
    public function __invoke(
        Request $request,
        Vendor $vendor,
        VendorStatementService $statementService
    ): View {
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

        return view('vendors.statement-print', [
            'vendor' => $vendor,
            'rows' => $statement['rows'],
            'summary' => $statement['summary'],
            'filters' => $statement['filters'],
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

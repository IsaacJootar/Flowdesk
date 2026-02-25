<?php

namespace App\Http\Controllers;

use App\Domains\Vendors\Models\Vendor;
use App\Services\VendorStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VendorStatementPrintController extends Controller
{
    public function __invoke(
        Request $request,
        Vendor $vendor,
        VendorStatementService $statementService
    ): View {
        Gate::authorize('exportStatements', $vendor);

        $from = $this->cleanDate($request->query('from'));
        $to = $this->cleanDate($request->query('to'));
        $invoiceStatus = (string) $request->query('invoice_status', 'all');

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
}

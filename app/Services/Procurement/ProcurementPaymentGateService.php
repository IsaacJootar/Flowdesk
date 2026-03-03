<?php

namespace App\Services\Procurement;

use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Requests\Models\SpendRequest;

class ProcurementPaymentGateService
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService,
        private readonly MandatoryPurchaseOrderPolicyService $mandatoryPurchaseOrderPolicyService,
    ) {
    }

    /**
     * @return array{allowed:bool,reason:string,metadata:array<string,mixed>}
     */
    public function evaluateForRequest(SpendRequest $request): array
    {
        $request->loadMissing([
            'purchaseOrders.vendorInvoices:id,purchase_order_id,invoice_number',
            'purchaseOrders.matchResults:id,purchase_order_id,vendor_invoice_id,match_status,match_score,mismatch_reason',
            'purchaseOrders.matchExceptions:id,invoice_match_result_id,purchase_order_id,vendor_invoice_id,exception_code,exception_status',
        ]);

        $mandatoryPoPolicy = $this->mandatoryPurchaseOrderPolicyService->evaluateForRequest($request);
        if ((bool) ($mandatoryPoPolicy['required'] ?? false) && $request->purchaseOrders->isEmpty()) {
            return [
                'allowed' => false,
                'reason' => (string) ($mandatoryPoPolicy['reason'] ?? 'Mandatory PO policy requires conversion before payout.'),
                'metadata' => [
                    'block_reason' => 'mandatory_po_policy',
                    'mandatory_po' => (array) ($mandatoryPoPolicy['context'] ?? []),
                ],
            ];
        }

        $controls = $this->settingsService->effectiveControls((int) $request->company_id);
        if (! (bool) ($controls['block_payment_on_mismatch'] ?? true)) {
            return [
                'allowed' => true,
                'reason' => '',
                'metadata' => ['mode' => 'block_disabled'],
            ];
        }

        $orders = $request->purchaseOrders;
        if ($orders->isEmpty()) {
            return [
                'allowed' => true,
                'reason' => '',
                'metadata' => ['mode' => 'no_procurement_context'],
            ];
        }

        foreach ($orders as $order) {
            $poNumber = (string) ($order->po_number ?? 'PO');
            $invoices = $order->vendorInvoices;

            if ($invoices->isEmpty()) {
                return [
                    'allowed' => false,
                    'reason' => sprintf('Procurement gate blocked payout: %s has no linked vendor invoice.', $poNumber),
                    'metadata' => [
                        'purchase_order_id' => (int) $order->id,
                        'purchase_order_number' => $poNumber,
                        'block_reason' => 'missing_linked_invoice',
                    ],
                ];
            }

            foreach ($invoices as $invoice) {
                $invoiceNumber = (string) ($invoice->invoice_number ?? 'invoice');
                $result = $order->matchResults
                    ->first(fn ($row) => (int) $row->vendor_invoice_id === (int) $invoice->id);

                if (! $result instanceof InvoiceMatchResult) {
                    return [
                        'allowed' => false,
                        'reason' => sprintf('Procurement gate blocked payout: %s / %s has no 3-way match result.', $poNumber, $invoiceNumber),
                        'metadata' => [
                            'purchase_order_id' => (int) $order->id,
                            'vendor_invoice_id' => (int) $invoice->id,
                            'purchase_order_number' => $poNumber,
                            'invoice_number' => $invoiceNumber,
                            'block_reason' => 'missing_match_result',
                        ],
                    ];
                }

                $status = (string) $result->match_status;
                if (! in_array($status, [InvoiceMatchResult::STATUS_MATCHED, InvoiceMatchResult::STATUS_OVERRIDDEN], true)) {
                    return [
                        'allowed' => false,
                        'reason' => sprintf('Procurement gate blocked payout: %s / %s is %s.', $poNumber, $invoiceNumber, str_replace('_', ' ', $status)),
                        'metadata' => [
                            'purchase_order_id' => (int) $order->id,
                            'vendor_invoice_id' => (int) $invoice->id,
                            'invoice_match_result_id' => (int) $result->id,
                            'purchase_order_number' => $poNumber,
                            'invoice_number' => $invoiceNumber,
                            'match_status' => $status,
                            'block_reason' => 'match_not_passed',
                        ],
                    ];
                }

                $openExceptionCount = $order->matchExceptions
                    ->where('invoice_match_result_id', (int) $result->id)
                    ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                    ->count();

                if ($openExceptionCount > 0) {
                    return [
                        'allowed' => false,
                        'reason' => sprintf('Procurement gate blocked payout: %s / %s has %d open match exception(s).', $poNumber, $invoiceNumber, $openExceptionCount),
                        'metadata' => [
                            'purchase_order_id' => (int) $order->id,
                            'vendor_invoice_id' => (int) $invoice->id,
                            'invoice_match_result_id' => (int) $result->id,
                            'purchase_order_number' => $poNumber,
                            'invoice_number' => $invoiceNumber,
                            'open_exception_count' => (int) $openExceptionCount,
                            'block_reason' => 'open_match_exceptions',
                        ],
                    ];
                }
            }
        }

        return [
            'allowed' => true,
            'reason' => '',
            'metadata' => ['mode' => 'procurement_gate_passed'],
        ];
    }
}
<?php

namespace App\Services\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkVendorInvoiceToPurchaseOrderService
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly EvaluateInvoiceThreeWayMatchService $evaluateInvoiceThreeWayMatchService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function link(User $actor, PurchaseOrder $order, VendorInvoice $invoice): PurchaseOrder
    {
        if ((int) $actor->company_id !== (int) $order->company_id || (int) $actor->company_id !== (int) $invoice->company_id) {
            throw ValidationException::withMessages([
                'invoice' => 'Purchase order or invoice does not belong to your company scope.',
            ]);
        }

        $controls = $this->settingsService->effectiveControls((int) $actor->company_id);
        $allowedRoles = (array) ($controls['invoice_link_allowed_roles'] ?? ['owner', 'finance']);
        if (! in_array(strtolower((string) $actor->role), $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role' => sprintf('Only [%s] can link vendor invoices to procurement orders.', implode(', ', $allowedRoles)),
            ]);
        }

        if ((int) $invoice->vendor_id !== (int) $order->vendor_id) {
            throw ValidationException::withMessages([
                'invoice' => 'Only vendor invoices from the same vendor can be linked to this order.',
            ]);
        }

        if ((string) $invoice->status === VendorInvoice::STATUS_VOID) {
            throw ValidationException::withMessages([
                'invoice' => 'Void invoices cannot be linked to procurement orders.',
            ]);
        }

        if (
            $invoice->purchase_order_id
            && (int) $invoice->purchase_order_id !== (int) $order->id
        ) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice is already linked to another purchase order.',
            ]);
        }

        $statusTransitioned = false;

        /** @var PurchaseOrder $updated */
        $updated = DB::transaction(function () use ($actor, $order, $invoice, &$statusTransitioned): PurchaseOrder {
            /** @var PurchaseOrder $lockedOrder */
            $lockedOrder = PurchaseOrder::query()
                ->whereKey((int) $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var VendorInvoice $lockedInvoice */
            $lockedInvoice = VendorInvoice::query()
                ->whereKey((int) $invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice->forceFill([
                'purchase_order_id' => (int) $lockedOrder->id,
                'updated_by' => (int) $actor->id,
            ])->save();

            // Once an invoice is linked, moving the PO into invoiced status keeps execution gates deterministic.
            if (in_array((string) $lockedOrder->po_status, [
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_PART_RECEIVED,
                PurchaseOrder::STATUS_RECEIVED,
            ], true)) {
                $lockedOrder->forceFill([
                    'po_status' => PurchaseOrder::STATUS_INVOICED,
                    'updated_by' => (int) $actor->id,
                ])->save();

                $statusTransitioned = true;
            }

            return $lockedOrder->fresh(['vendorInvoices']);
        });

        $matchResult = $this->evaluateInvoiceThreeWayMatchService->evaluate(
            $actor,
            $updated,
            $invoice->fresh()
        );

        $this->tenantAuditLogger->log(
            companyId: (int) $order->company_id,
            action: 'tenant.procurement.vendor_invoice.linked',
            actor: $actor,
            description: 'Vendor invoice linked to procurement purchase order.',
            entityType: VendorInvoice::class,
            entityId: (int) $invoice->id,
            metadata: [
                'purchase_order_id' => (int) $order->id,
                'po_number' => (string) $order->po_number,
                'vendor_invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) $invoice->invoice_number,
                'status_transitioned' => $statusTransitioned,
                'match_status' => (string) $matchResult->match_status,
                'match_score' => (float) $matchResult->match_score,
            ],
        );

        if ($statusTransitioned) {
            $this->tenantAuditLogger->log(
                companyId: (int) $order->company_id,
                action: 'tenant.procurement.purchase_order.invoiced',
                actor: $actor,
                description: 'Purchase order moved to invoiced status after invoice linkage.',
                entityType: PurchaseOrder::class,
                entityId: (int) $order->id,
                metadata: [
                    'po_number' => (string) $order->po_number,
                    'vendor_invoice_id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                ],
            );
        }

        return $updated;
    }
}
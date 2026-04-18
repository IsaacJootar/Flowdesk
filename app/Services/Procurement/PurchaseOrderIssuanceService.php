<?php

namespace App\Services\Procurement;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use App\Services\RequestCommunicationLogger;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderIssuanceService
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly RequestCommunicationLogger $requestCommunicationLogger,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function issue(User $actor, PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        if ((int) $actor->company_id !== (int) $order->company_id) {
            throw ValidationException::withMessages([
                'order' => 'Purchase order does not belong to your company scope.',
            ]);
        }

        $controls = $this->settingsService->effectiveControls((int) $actor->company_id);
        $allowedRoles = (array) ($controls['issue_allowed_roles'] ?? ['owner', 'finance']);
        if (! in_array(strtolower((string) $actor->role), $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'order' => sprintf('Only [%s] can issue procurement orders for this tenant.', implode(', ', $allowedRoles)),
            ]);
        }

        if ((string) $order->po_status !== PurchaseOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'order' => 'Only draft procurement orders can be issued.',
            ]);
        }

        $itemCount = (int) $order->items()->count();
        if ($itemCount < 1) {
            throw ValidationException::withMessages([
                'order' => 'At least one line item is required before issuing the order.',
            ]);
        }

        if ((int) $order->total_amount <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Order total must be greater than zero before issue.',
            ]);
        }

        $commitment = null;
        $issuedOrder = DB::transaction(function () use ($actor, $order, $reason, $controls, &$commitment): PurchaseOrder {
            $order->forceFill([
                'po_status' => PurchaseOrder::STATUS_ISSUED,
                'issued_at' => now(),
                'updated_by' => (int) $actor->id,
            ])->save();

            // Commitment posting is a financial-control switch so tenants can phase adoption safely.
            if ((bool) ($controls['auto_post_commitment_on_issue'] ?? true)) {
                $commitment = ProcurementCommitment::query()->create([
                    'company_id' => (int) $order->company_id,
                    'purchase_order_id' => (int) $order->id,
                    'department_budget_id' => $order->department_budget_id ? (int) $order->department_budget_id : null,
                    'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
                    'amount' => (int) $order->total_amount,
                    'currency_code' => strtoupper((string) ($order->currency_code ?: 'NGN')),
                    'effective_at' => now(),
                    'metadata' => [
                        'source' => 'po_issue',
                        'po_number' => (string) $order->po_number,
                        'issue_reason' => $reason ? trim($reason) : null,
                    ],
                    'created_by' => (int) $actor->id,
                    'updated_by' => (int) $actor->id,
                ]);
            }

            return $order->fresh(['items', 'commitments']);
        });

        $this->tenantAuditLogger->log(
            companyId: (int) $order->company_id,
            action: 'tenant.procurement.purchase_order.issued',
            actor: $actor,
            description: 'Procurement order issued and moved from draft to issued.',
            entityType: PurchaseOrder::class,
            entityId: (int) $order->id,
            metadata: [
                'po_number' => (string) $issuedOrder->po_number,
                'amount' => (int) $issuedOrder->total_amount,
                'reason' => $reason ? trim($reason) : null,
            ],
        );

        if ($commitment) {
            $this->tenantAuditLogger->log(
                companyId: (int) $order->company_id,
                action: 'tenant.procurement.commitment.posted',
                actor: $actor,
                description: 'Budget commitment posted from purchase order issuance.',
                entityType: ProcurementCommitment::class,
                entityId: (int) $commitment->id,
                metadata: [
                    'purchase_order_id' => (int) $issuedOrder->id,
                    'po_number' => (string) $issuedOrder->po_number,
                    'department_budget_id' => $commitment->department_budget_id ? (int) $commitment->department_budget_id : null,
                    'amount' => (int) $commitment->amount,
                    'currency_code' => (string) $commitment->currency_code,
                ],
            );
        }

        // Notify the request requester that their PO has been formally issued.
        $spendRequest = $issuedOrder->spendRequest()->withoutGlobalScopes()->first();
        if ($spendRequest && $spendRequest->requested_by) {
            $communicationSettings = CompanyCommunicationSetting::query()
                ->firstOrCreate(
                    ['company_id' => (int) $issuedOrder->company_id],
                    CompanyCommunicationSetting::defaultAttributes()
                );

            $this->requestCommunicationLogger->log(
                request: $spendRequest,
                event: 'request.purchase_order.issued',
                channels: $communicationSettings->selectableChannels() ?: ['in_app'],
                recipientUserIds: [(int) $spendRequest->requested_by],
                requestApprovalId: null,
                metadata: [
                    'request_code' => (string) $spendRequest->request_code,
                    'po_number' => (string) $issuedOrder->po_number,
                    'amount' => (int) $issuedOrder->total_amount,
                ],
            );
        }

        return $issuedOrder;
    }
}

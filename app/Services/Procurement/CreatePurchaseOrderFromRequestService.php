<?php

namespace App\Services\Procurement;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\RequestCommunicationLogger;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrderFromRequestService
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
    public function createFromRequest(User $actor, SpendRequest $request): PurchaseOrder
    {
        Gate::forUser($actor)->authorize('convertToPurchaseOrder', $request);

        if ((int) $actor->company_id !== (int) $request->company_id) {
            throw ValidationException::withMessages([
                'request' => 'Request does not belong to your company scope.',
            ]);
        }

        $controls = $this->settingsService->effectiveControls((int) $actor->company_id);
        $allowedStatuses = (array) ($controls['conversion_allowed_statuses'] ?? ['approved']);
        $requestStatus = strtolower((string) $request->status);

        if (! in_array($requestStatus, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'request' => sprintf(
                    'Only requests in [%s] can be converted to procurement orders for this tenant.',
                    implode(', ', $allowedStatuses)
                ),
            ]);
        }

        $existing = PurchaseOrder::query()
            ->where('spend_request_id', (int) $request->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'request' => sprintf('Request already has a linked procurement order (%s).', (string) $existing->po_number),
            ]);
        }

        $vendorId = $this->resolveVendorId($request);
        // Vendor remains mandatory because purchase_orders.vendor_id is a required control column.
        if (! $vendorId) {
            throw ValidationException::withMessages([
                'request' => 'Vendor is required before converting this request to a procurement order.',
            ]);
        }

        $amount = (int) ($request->approved_amount ?: $request->amount);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'request' => 'Approved request amount must be greater than zero before conversion.',
            ]);
        }

        $request = $request->loadMissing(['items']);

        $po = DB::transaction(function () use ($actor, $request, $controls, $vendorId): PurchaseOrder {
            $lineItems = $this->buildLineItems($request);
            $subtotal = array_sum(array_column($lineItems, 'line_total'));
            $deliveryDays = max(1, (int) ($controls['default_expected_delivery_days'] ?? 14));

            $po = PurchaseOrder::query()->create([
                'company_id' => (int) $request->company_id,
                'spend_request_id' => (int) $request->id,
                'department_budget_id' => $this->resolveDepartmentBudgetId($request),
                'vendor_id' => $vendorId,
                'po_number' => $this->generatePoNumber((int) $request->company_id),
                'po_status' => PurchaseOrder::STATUS_DRAFT,
                'currency_code' => strtoupper((string) ($request->currency ?: 'NGN')),
                'subtotal_amount' => $subtotal,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'expected_delivery_at' => now()->addDays($deliveryDays)->toDateString(),
                'metadata' => [
                    'source' => 'request_conversion',
                    'source_request_code' => (string) $request->request_code,
                    'request_status_at_conversion' => (string) $request->status,
                ],
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]);

            foreach ($lineItems as $line) {
                PurchaseOrderItem::query()->create([
                    'company_id' => (int) $request->company_id,
                    'purchase_order_id' => (int) $po->id,
                    'line_number' => (int) $line['line_number'],
                    'item_description' => (string) $line['item_description'],
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => (int) $line['unit_price'],
                    'line_total' => (int) $line['line_total'],
                    'currency_code' => strtoupper((string) ($request->currency ?: 'NGN')),
                    'received_quantity' => 0,
                    'received_total' => 0,
                    'metadata' => (array) ($line['metadata'] ?? []),
                ]);
            }

            return $po->fresh(['items']);
        });

        $this->tenantAuditLogger->log(
            companyId: (int) $request->company_id,
            action: 'tenant.procurement.purchase_order.created_from_request',
            actor: $actor,
            description: 'Approved request converted to procurement purchase order draft.',
            entityType: PurchaseOrder::class,
            entityId: (int) $po->id,
            metadata: [
                'request_id' => (int) $request->id,
                'request_code' => (string) $request->request_code,
                'po_number' => (string) $po->po_number,
                'vendor_id' => (int) $po->vendor_id,
                'amount' => (int) $po->total_amount,
            ],
        );

        $communicationSettings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $request->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );

        $this->requestCommunicationLogger->log(
            request: $request,
            event: 'request.purchase_order.created',
            channels: $communicationSettings->selectableChannels() ?: ['in_app'],
            recipientUserIds: [(int) $request->requested_by],
            requestApprovalId: null,
            metadata: [
                'request_code' => (string) $request->request_code,
                'po_number' => (string) $po->po_number,
                'amount' => (int) $po->total_amount,
            ],
        );

        return $po;
    }

    private function resolveVendorId(SpendRequest $request): ?int
    {
        $directVendorId = (int) ($request->vendor_id ?? 0);
        if ($directVendorId > 0) {
            return $directVendorId;
        }

        $itemVendorId = (int) ($request->items
            ->first(fn ($item): bool => (int) ($item->vendor_id ?? 0) > 0)
            ?->vendor_id ?? 0);

        return $itemVendorId > 0 ? $itemVendorId : null;
    }

    /**
     * @return array<int, array{line_number:int,item_description:string,quantity:float,unit_price:int,line_total:int,metadata:array<string,mixed>}>
     */
    private function buildLineItems(SpendRequest $request): array
    {
        if ($request->items->isNotEmpty()) {
            return $request->items
                ->values()
                ->map(function ($item, int $index): array {
                    $quantity = max(1, (int) ($item->quantity ?? 1));
                    $unitPrice = max(0, (int) ($item->unit_cost ?? 0));
                    $lineTotal = max(0, (int) ($item->line_total ?? ($quantity * $unitPrice)));

                    return [
                        'line_number' => $index + 1,
                        'item_description' => (string) ($item->item_name ?: 'Line Item '.($index + 1)),
                        'quantity' => (float) $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'metadata' => [
                            'source_request_item_id' => (int) $item->id,
                            'source_category' => (string) ($item->category ?? ''),
                        ],
                    ];
                })
                ->all();
        }

        $amount = max(0, (int) ($request->approved_amount ?: $request->amount));

        return [[
            'line_number' => 1,
            'item_description' => Str::limit((string) $request->title, 255, ''),
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
            'metadata' => [
                'generated' => true,
            ],
        ]];
    }

    private function resolveDepartmentBudgetId(SpendRequest $request): ?int
    {
        $referenceDate = optional($request->submitted_at)->toDateString() ?: now()->toDateString();

        $budget = DepartmentBudget::query()
            ->where('company_id', (int) $request->company_id)
            ->where('department_id', (int) $request->department_id)
            ->where('status', 'active')
            ->whereDate('period_start', '<=', $referenceDate)
            ->whereDate('period_end', '>=', $referenceDate)
            ->latest('period_start')
            ->first();

        return $budget ? (int) $budget->id : null;
    }

    private function generatePoNumber(int $companyId): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            $exists = PurchaseOrder::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('po_number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(Str::random(8));
    }
}


<?php

namespace App\Services\Procurement;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\RequestCommunicationLogger;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateGoodsReceiptService
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly RequestCommunicationLogger $requestCommunicationLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @throws ValidationException
     */
    public function create(User $actor, PurchaseOrder $order, array $input): GoodsReceipt
    {
        if ((int) $actor->company_id !== (int) $order->company_id) {
            throw ValidationException::withMessages([
                'order' => 'Purchase order does not belong to your company scope.',
            ]);
        }

        $controls = $this->settingsService->effectiveControls((int) $actor->company_id);
        $allowedRoles = (array) ($controls['receipt_allowed_roles'] ?? ['owner', 'finance', 'manager']);
        if (! in_array(strtolower((string) $actor->role), $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role' => sprintf('Only [%s] can record goods receipts for this tenant.', implode(', ', $allowedRoles)),
            ]);
        }

        if (! in_array((string) $order->po_status, [
            PurchaseOrder::STATUS_ISSUED,
            PurchaseOrder::STATUS_PART_RECEIVED,
            PurchaseOrder::STATUS_INVOICED,
        ], true)) {
            throw ValidationException::withMessages([
                'order' => 'Only issued or active procurement orders can receive goods.',
            ]);
        }

        $validated = Validator::make($input, [
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'min:1'],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.received_unit_cost' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $allowOverReceipt = (bool) ($controls['allow_over_receipt'] ?? false);

        $processedLineCount = 0;
        $processedQuantity = 0.0;
        $processedValue = 0;

        $receipt = DB::transaction(function () use (
            $actor,
            $order,
            $validated,
            $allowOverReceipt,
            &$processedLineCount,
            &$processedQuantity,
            &$processedValue
        ): GoodsReceipt {
            /** @var PurchaseOrder $lockedOrder */
            $lockedOrder = PurchaseOrder::query()
                ->whereKey((int) $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var \Illuminate\Support\Collection<int, PurchaseOrderItem> $orderItems */
            $orderItems = PurchaseOrderItem::query()
                ->where('purchase_order_id', (int) $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $receipt = GoodsReceipt::query()->create([
                'company_id' => (int) $order->company_id,
                'purchase_order_id' => (int) $order->id,
                'receipt_number' => $this->generateReceiptNumber((int) $order->company_id),
                'received_at' => (string) $validated['received_at'],
                'received_by_user_id' => (int) $actor->id,
                'receipt_status' => GoodsReceipt::STATUS_CONFIRMED,
                'notes' => $this->nullableString($validated['notes'] ?? null),
                'metadata' => [
                    'source' => 'manual_receipt_entry',
                    'allow_over_receipt' => $allowOverReceipt,
                ],
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]);

            $errors = [];
            foreach ((array) $validated['items'] as $index => $line) {
                $itemId = (int) ($line['purchase_order_item_id'] ?? 0);
                $receivedQuantity = round((float) ($line['received_quantity'] ?? 0), 2);
                if ($receivedQuantity <= 0) {
                    continue;
                }

                /** @var PurchaseOrderItem|null $orderItem */
                $orderItem = $orderItems->get($itemId);
                if (! $orderItem) {
                    $errors['items.'.$index.'.purchase_order_item_id'] = 'Selected order line is invalid.';
                    continue;
                }

                $orderedQuantity = (float) $orderItem->quantity;
                $currentReceived = (float) $orderItem->received_quantity;
                $remainingQuantity = max(0, $orderedQuantity - $currentReceived);

                // This guardrail prevents silent quantity drift unless tenant explicitly allows over-receipt.
                if (! $allowOverReceipt && $receivedQuantity > $remainingQuantity) {
                    $errors['items.'.$index.'.received_quantity'] = sprintf(
                        'Received quantity %.2f exceeds remaining quantity %.2f for line %d.',
                        $receivedQuantity,
                        $remainingQuantity,
                        (int) $orderItem->line_number
                    );
                    continue;
                }

                $unitCost = (int) ($line['received_unit_cost'] ?? $orderItem->unit_price);
                if ($unitCost <= 0) {
                    $errors['items.'.$index.'.received_unit_cost'] = 'Received unit cost must be greater than zero.';
                    continue;
                }

                $lineTotal = (int) round($receivedQuantity * $unitCost);

                GoodsReceiptItem::query()->create([
                    'company_id' => (int) $order->company_id,
                    'goods_receipt_id' => (int) $receipt->id,
                    'purchase_order_item_id' => (int) $orderItem->id,
                    'received_quantity' => $receivedQuantity,
                    'received_unit_cost' => $unitCost,
                    'received_total' => $lineTotal,
                    'metadata' => [
                        'line_number' => (int) $orderItem->line_number,
                    ],
                ]);

                $orderItem->forceFill([
                    'received_quantity' => round($currentReceived + $receivedQuantity, 2),
                    'received_total' => (int) $orderItem->received_total + $lineTotal,
                ])->save();

                $processedLineCount++;
                $processedQuantity += $receivedQuantity;
                $processedValue += $lineTotal;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($processedLineCount === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Enter a received quantity greater than zero for at least one line item.',
                ]);
            }

            $orderItems = PurchaseOrderItem::query()
                ->where('purchase_order_id', (int) $order->id)
                ->get();

            $allFulfilled = $orderItems->every(function (PurchaseOrderItem $item): bool {
                return (float) $item->received_quantity >= (float) $item->quantity;
            });

            $nextStatus = $this->resolveNextOrderStatus((string) $lockedOrder->po_status, $allFulfilled);
            $lockedOrder->forceFill([
                'po_status' => $nextStatus,
                'updated_by' => (int) $actor->id,
            ])->save();

            return $receipt->fresh(['items.orderItem', 'order.items']);
        });

        $this->tenantAuditLogger->log(
            companyId: (int) $order->company_id,
            action: 'tenant.procurement.goods_receipt.created',
            actor: $actor,
            description: 'Goods receipt recorded against procurement order.',
            entityType: GoodsReceipt::class,
            entityId: (int) $receipt->id,
            metadata: [
                'purchase_order_id' => (int) $order->id,
                'po_number' => (string) $order->po_number,
                'receipt_number' => (string) $receipt->receipt_number,
                'line_count' => $processedLineCount,
                'received_quantity' => round($processedQuantity, 2),
                'received_value' => $processedValue,
                'allow_over_receipt' => $allowOverReceipt,
            ],
        );

        // Notify the requester that goods have been received against their order.
        $spendRequest = $order->spendRequest()->withoutGlobalScopes()->first();
        if ($spendRequest && $spendRequest->requested_by) {
            $communicationSettings = CompanyCommunicationSetting::query()
                ->firstOrCreate(
                    ['company_id' => (int) $order->company_id],
                    CompanyCommunicationSetting::defaultAttributes()
                );

            $this->requestCommunicationLogger->log(
                request: $spendRequest,
                event: 'request.goods_received',
                channels: $communicationSettings->selectableChannels() ?: ['in_app'],
                recipientUserIds: [(int) $spendRequest->requested_by],
                requestApprovalId: null,
                metadata: [
                    'request_code' => (string) $spendRequest->request_code,
                    'po_number' => (string) $order->po_number,
                    'receipt_number' => (string) $receipt->receipt_number,
                    'received_value' => $processedValue,
                ],
            );
        }

        return $receipt;
    }

    private function resolveNextOrderStatus(string $currentStatus, bool $allFulfilled): string
    {
        if ($currentStatus === PurchaseOrder::STATUS_INVOICED) {
            return PurchaseOrder::STATUS_INVOICED;
        }

        return $allFulfilled
            ? PurchaseOrder::STATUS_RECEIVED
            : PurchaseOrder::STATUS_PART_RECEIVED;
    }

    private function generateReceiptNumber(int $companyId): string
    {
        $prefix = 'GR-'.now()->format('Ym').'-';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            $exists = GoodsReceipt::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('receipt_number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(Str::random(8));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
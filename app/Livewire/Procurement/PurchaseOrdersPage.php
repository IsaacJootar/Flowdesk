<?php

namespace App\Livewire\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Procurement\CreateGoodsReceiptService;
use App\Services\Procurement\LinkVendorInvoiceToPurchaseOrderService;
use App\Services\Procurement\ProcurementControlSettingsService;
use App\Services\Procurement\PurchaseOrderIssuanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class PurchaseOrdersPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 10;

    public ?int $selectedOrderId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedOrder = null;

    public bool $showDetailsModal = false;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /**
     * @var array{received_at:string,notes:string,items:array<int,array<string,mixed>>}
     */
    public array $receiptForm = [
        'received_at' => '',
        'notes' => '',
        'items' => [],
    ];

    public ?int $selectedVendorInvoiceId = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $deepLinkSearch = trim((string) request()->query('search', ''));
        if ($deepLinkSearch !== '') {
            $this->search = mb_substr($deepLinkSearch, 0, 120);
        }

        $openOrderId = (int) request()->query('open_order_id', 0);
        if ($openOrderId > 0 && PurchaseOrder::query()->whereKey($openOrderId)->exists()) {
            $this->readyToLoad = true;
            $this->openDetails($openOrderId);
        }
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function openDetails(int $orderId): void
    {
        $order = PurchaseOrder::query()
            ->with([
                'request:id,request_code,title,status',
                'vendor:id,name',
                'items',
                'commitments',
                'receipts.items.orderItem',
                'vendorInvoices',
            ])
            ->findOrFail($orderId);

        $this->selectedOrderId = (int) $order->id;
        $this->fillSelectedOrder($order);
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
        $this->receiptForm = [
            'received_at' => '',
            'notes' => '',
            'items' => [],
        ];
        $this->selectedVendorInvoiceId = null;
    }

    public function issueSelectedOrder(PurchaseOrderIssuanceService $issuanceService): void
    {
        if (! $this->selectedOrderId) {
            return;
        }

        $order = PurchaseOrder::query()->with(['items', 'commitments'])->findOrFail($this->selectedOrderId);

        try {
            $issued = $issuanceService->issue(auth()->user(), $order, 'Issued from procurement orders page');
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->setFeedbackError($message !== '' ? $message : 'Unable to issue order right now.');

            return;
        } catch (Throwable) {
            $this->setFeedbackError('Unable to issue order right now.');

            return;
        }

        $this->setFeedback(sprintf('Purchase order %s issued.', (string) $issued->po_number));
        $this->refreshSelectedOrder();
        $this->resetPage();
    }

    public function submitGoodsReceipt(CreateGoodsReceiptService $receiptService): void
    {
        if (! $this->selectedOrderId) {
            return;
        }

        $validated = $this->validate([
            'receiptForm.received_at' => ['required', 'date'],
            'receiptForm.notes' => ['nullable', 'string', 'max:2000'],
            'receiptForm.items' => ['required', 'array', 'min:1'],
            'receiptForm.items.*.purchase_order_item_id' => ['required', 'integer', 'min:1'],
            'receiptForm.items.*.receive_quantity' => ['required', 'numeric', 'min:0'],
            'receiptForm.items.*.received_unit_cost' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = PurchaseOrder::query()->findOrFail($this->selectedOrderId);

        $lines = collect((array) $validated['receiptForm']['items'])
            ->map(static function (array $line): array {
                return [
                    'purchase_order_item_id' => (int) ($line['purchase_order_item_id'] ?? 0),
                    'received_quantity' => (float) ($line['receive_quantity'] ?? 0),
                    'received_unit_cost' => isset($line['received_unit_cost']) && (string) $line['received_unit_cost'] !== ''
                        ? (int) $line['received_unit_cost']
                        : null,
                ];
            })
            ->all();

        try {
            $receipt = $receiptService->create(auth()->user(), $order, [
                'received_at' => (string) $validated['receiptForm']['received_at'],
                'notes' => (string) ($validated['receiptForm']['notes'] ?? ''),
                'items' => $lines,
            ]);
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->setFeedbackError($message !== '' ? $message : 'Unable to record goods receipt.');

            return;
        } catch (Throwable) {
            $this->setFeedbackError('Unable to record goods receipt.');

            return;
        }

        $this->setFeedback(sprintf('Goods receipt %s recorded.', (string) $receipt->receipt_number));
        $this->refreshSelectedOrder();
    }

    public function linkSelectedVendorInvoice(LinkVendorInvoiceToPurchaseOrderService $linkService): void
    {
        if (! $this->selectedOrderId || ! $this->selectedVendorInvoiceId) {
            $this->setFeedbackError('Select a vendor invoice to link first.');

            return;
        }

        $order = PurchaseOrder::query()->findOrFail($this->selectedOrderId);
        $invoice = VendorInvoice::query()->findOrFail($this->selectedVendorInvoiceId);

        try {
            $updated = $linkService->link(auth()->user(), $order, $invoice);
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->setFeedbackError($message !== '' ? $message : 'Unable to link invoice to purchase order.');

            return;
        } catch (Throwable) {
            $this->setFeedbackError('Unable to link invoice to purchase order.');

            return;
        }

        $this->setFeedback(sprintf('Invoice %s linked to %s.', (string) $invoice->invoice_number, (string) $updated->po_number));
        $this->refreshSelectedOrder();
    }

    public function render(ProcurementControlSettingsService $settingsService): View
    {
        $query = PurchaseOrder::query()
            ->with(['request:id,request_code,title,status', 'vendor:id,name'])
            ->withCount(['items', 'commitments', 'receipts', 'vendorInvoices'])
            ->when($this->search !== '', function ($builder): void {
                $builder->where(function ($inner): void {
                    $inner->where('po_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('request', fn ($requestQuery) => $requestQuery->where('request_code', 'like', '%'.$this->search.'%')->orWhere('title', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('po_status', $this->statusFilter))
            ->latest('id');

        $orders = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : PurchaseOrder::query()->whereRaw('1=0')->paginate($this->perPage);

        $summary = $this->readyToLoad
            ? [
                'total' => (clone $query)->count(),
                'draft' => (clone $query)->where('po_status', PurchaseOrder::STATUS_DRAFT)->count(),
                'issued' => (clone $query)->where('po_status', PurchaseOrder::STATUS_ISSUED)->count(),
                'receiving' => (clone $query)->where('po_status', PurchaseOrder::STATUS_PART_RECEIVED)->count(),
            ]
            : ['total' => 0, 'draft' => 0, 'issued' => 0, 'receiving' => 0];

        $controls = $settingsService->effectiveControls((int) auth()->user()->company_id);

        return view('livewire.procurement.purchase-orders-page', [
            'orders' => $orders,
            'statuses' => PurchaseOrder::STATUSES,
            'summary' => $summary,
            'issueRoles' => (array) $controls['issue_allowed_roles'],
            'receiptRoles' => (array) $controls['receipt_allowed_roles'],
            'invoiceLinkRoles' => (array) $controls['invoice_link_allowed_roles'],
            'allowOverReceipt' => (bool) $controls['allow_over_receipt'],
        ]);
    }

    private function fillSelectedOrder(PurchaseOrder $order): void
    {
        $order = $order->loadMissing([
            'request:id,request_code,title,status',
            'vendor:id,name',
            'items',
            'commitments',
            'receipts.items.orderItem',
            'vendorInvoices',
        ]);

        $controls = app(ProcurementControlSettingsService::class)->effectiveControls((int) auth()->user()->company_id);

        $role = strtolower((string) auth()->user()->role);
        $canIssue = in_array($role, (array) ($controls['issue_allowed_roles'] ?? []), true)
            && (string) $order->po_status === PurchaseOrder::STATUS_DRAFT;

        $canReceive = in_array($role, (array) ($controls['receipt_allowed_roles'] ?? []), true)
            && in_array((string) $order->po_status, [
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_PART_RECEIVED,
                PurchaseOrder::STATUS_INVOICED,
            ], true);

        $items = $order->items
            ->map(function ($item): array {
                $orderedQty = (float) $item->quantity;
                $receivedQty = (float) $item->received_quantity;

                return [
                    'id' => (int) $item->id,
                    'line_number' => (int) $item->line_number,
                    'item_description' => (string) $item->item_description,
                    'quantity' => $orderedQty,
                    'received_quantity' => $receivedQty,
                    'remaining_quantity' => max(0, round($orderedQty - $receivedQty, 2)),
                    'unit_price' => (int) $item->unit_price,
                    'line_total' => (int) $item->line_total,
                    'received_total' => (int) $item->received_total,
                ];
            })
            ->values()
            ->all();

        $linkedInvoices = $order->vendorInvoices
            ->sortByDesc('invoice_date')
            ->values()
            ->map(static function (VendorInvoice $invoice): array {
                return [
                    'id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'invoice_date' => optional($invoice->invoice_date)->format('M d, Y'),
                    'status' => (string) $invoice->status,
                    'currency' => strtoupper((string) ($invoice->currency ?: 'NGN')),
                    'total_amount' => (int) $invoice->total_amount,
                    'outstanding_amount' => (int) $invoice->outstanding_amount,
                ];
            })
            ->all();

        $selectableInvoices = VendorInvoice::query()
            ->where('company_id', (int) $order->company_id)
            ->where('vendor_id', (int) $order->vendor_id)
            ->where('status', '!=', VendorInvoice::STATUS_VOID)
            ->whereNull('purchase_order_id')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $selectableInvoiceRows = $selectableInvoices
            ->map(static function (VendorInvoice $invoice): array {
                return [
                    'id' => (int) $invoice->id,
                    'label' => sprintf(
                        '%s | %s %s | %s',
                        (string) $invoice->invoice_number,
                        strtoupper((string) ($invoice->currency ?: 'NGN')),
                        number_format((int) $invoice->total_amount),
                        optional($invoice->invoice_date)->format('M d, Y') ?: '-'
                    ),
                ];
            })
            ->values()
            ->all();

        $canLinkInvoice = in_array($role, (array) ($controls['invoice_link_allowed_roles'] ?? []), true)
            && $selectableInvoices->isNotEmpty();

        $receipts = $order->receipts
            ->sortByDesc('received_at')
            ->values()
            ->map(function ($receipt): array {
                $totalQuantity = (float) $receipt->items->sum('received_quantity');
                $totalValue = (int) $receipt->items->sum('received_total');

                return [
                    'id' => (int) $receipt->id,
                    'receipt_number' => (string) $receipt->receipt_number,
                    'received_at' => optional($receipt->received_at)->format('M d, Y H:i'),
                    'line_count' => (int) $receipt->items->count(),
                    'received_quantity' => round($totalQuantity, 2),
                    'received_total' => $totalValue,
                    'status' => (string) $receipt->receipt_status,
                    'notes' => (string) ($receipt->notes ?? ''),
                ];
            })
            ->all();

        $this->selectedOrder = [
            'id' => (int) $order->id,
            'po_number' => (string) $order->po_number,
            'po_status' => (string) $order->po_status,
            'currency_code' => (string) $order->currency_code,
            'subtotal_amount' => (int) $order->subtotal_amount,
            'total_amount' => (int) $order->total_amount,
            'issued_at' => optional($order->issued_at)->format('M d, Y H:i'),
            'expected_delivery_at' => optional($order->expected_delivery_at)->format('M d, Y'),
            'request_code' => (string) ($order->request?->request_code ?? '-'),
            'request_title' => (string) ($order->request?->title ?? '-'),
            'request_status' => (string) ($order->request?->status ?? '-'),
            'vendor_name' => (string) ($order->vendor?->name ?? '-'),
            'items_count' => (int) $order->items->count(),
            'commitment_count' => (int) $order->commitments->count(),
            'commitment_total' => (int) $order->commitments->sum('amount'),
            'receipt_count' => (int) $order->receipts->count(),
            'linked_invoice_count' => (int) $order->vendorInvoices->count(),
            'can_issue' => $canIssue,
            'can_receive' => $canReceive,
            'can_link_invoice' => $canLinkInvoice,
            'allow_over_receipt' => (bool) ($controls['allow_over_receipt'] ?? false),
            'items' => $items,
            'commitments' => $order->commitments->map(fn ($commitment): array => [
                'status' => (string) $commitment->commitment_status,
                'amount' => (int) $commitment->amount,
                'effective_at' => optional($commitment->effective_at)->format('M d, Y H:i'),
            ])->values()->all(),
            'receipts' => $receipts,
            'linked_invoices' => $linkedInvoices,
            'selectable_invoices' => $selectableInvoiceRows,
            'timeline' => $this->buildTimeline($order),
        ];

        $selectableIds = collect($selectableInvoiceRows)->pluck('id')->all();
        if (! $this->selectedVendorInvoiceId || ! in_array($this->selectedVendorInvoiceId, $selectableIds, true)) {
            $this->selectedVendorInvoiceId = $selectableIds !== [] ? (int) $selectableIds[0] : null;
        }

        $this->initializeReceiptForm($items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function initializeReceiptForm(array $items): void
    {
        $this->receiptForm = [
            'received_at' => now()->format('Y-m-d\TH:i'),
            'notes' => '',
            'items' => collect($items)->map(static function (array $item): array {
                return [
                    'purchase_order_item_id' => (int) ($item['id'] ?? 0),
                    'line_number' => (int) ($item['line_number'] ?? 0),
                    'item_description' => (string) ($item['item_description'] ?? ''),
                    'remaining_quantity' => (float) ($item['remaining_quantity'] ?? 0),
                    'receive_quantity' => 0,
                    'received_unit_cost' => (int) ($item['unit_price'] ?? 0),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(PurchaseOrder $order): array
    {
        $events = collect([]);

        $events->push([
            'label' => 'PO created',
            'at' => optional($order->created_at)->format('M d, Y H:i'),
            'at_sort' => optional($order->created_at)?->timestamp ?? 0,
            'meta' => (string) $order->po_number,
        ]);

        if ($order->issued_at) {
            $events->push([
                'label' => 'PO issued',
                'at' => optional($order->issued_at)->format('M d, Y H:i'),
                'at_sort' => optional($order->issued_at)?->timestamp ?? 0,
                'meta' => strtoupper((string) ($order->currency_code ?: 'NGN')).' '.number_format((int) $order->total_amount),
            ]);
        }

        foreach ($order->commitments as $commitment) {
            $events->push([
                'label' => 'Commitment posted',
                'at' => optional($commitment->effective_at)->format('M d, Y H:i'),
                'at_sort' => optional($commitment->effective_at)?->timestamp ?? 0,
                'meta' => number_format((int) $commitment->amount).' '.strtoupper((string) ($commitment->currency_code ?: 'NGN')),
            ]);
        }

        foreach ($order->receipts as $receipt) {
            $events->push([
                'label' => 'Goods received',
                'at' => optional($receipt->received_at)->format('M d, Y H:i'),
                'at_sort' => optional($receipt->received_at)?->timestamp ?? 0,
                'meta' => (string) $receipt->receipt_number,
            ]);
        }

        foreach ($order->vendorInvoices as $invoice) {
            $events->push([
                'label' => 'Vendor invoice linked',
                'at' => optional($invoice->updated_at)->format('M d, Y H:i'),
                'at_sort' => optional($invoice->updated_at)?->timestamp ?? 0,
                'meta' => (string) $invoice->invoice_number,
            ]);
        }

        return $events
            ->sortByDesc('at_sort')
            ->values()
            ->map(static function (array $row): array {
                unset($row['at_sort']);

                return $row;
            })
            ->all();
    }

    private function refreshSelectedOrder(): void
    {
        if (! $this->selectedOrderId) {
            return;
        }

        $order = PurchaseOrder::query()
            ->with([
                'request:id,request_code,title,status',
                'vendor:id,name',
                'items',
                'commitments',
                'receipts.items.orderItem',
                'vendorInvoices',
            ])
            ->findOrFail($this->selectedOrderId);

        $this->fillSelectedOrder($order);
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }

    private function canAccessPage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }
}

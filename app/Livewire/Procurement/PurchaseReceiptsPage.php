<?php

namespace App\Livewire\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseReceiptsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?string $receivedFrom = null;

    public ?string $receivedTo = null;

    public int $perPage = 10;

    public bool $showDetailsModal = false;

    public ?int $selectedReceiptId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedReceipt = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
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

    public function updatedReceivedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedReceivedTo(): void
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

    public function openDetails(int $receiptId): void
    {
        $receipt = GoodsReceipt::query()
            ->with([
                'order:id,po_number,po_status,currency_code,total_amount,vendor_id',
                'order.vendor:id,name',
                'order.vendorInvoices:id,purchase_order_id,invoice_number,status,currency,total_amount,outstanding_amount,invoice_date',
                'receiver:id,name',
                'items.orderItem:id,line_number,item_description,quantity,unit_price',
            ])
            ->findOrFail($receiptId);

        $this->selectedReceiptId = (int) $receipt->id;
        $this->selectedReceipt = [
            'id' => (int) $receipt->id,
            'receipt_number' => (string) $receipt->receipt_number,
            'receipt_status' => (string) $receipt->receipt_status,
            'received_at' => optional($receipt->received_at)->format('M d, Y H:i'),
            'receiver' => (string) ($receipt->receiver?->name ?? '-'),
            'notes' => (string) ($receipt->notes ?? ''),
            'po_number' => (string) ($receipt->order?->po_number ?? '-'),
            'po_status' => (string) ($receipt->order?->po_status ?? '-'),
            'vendor_name' => (string) ($receipt->order?->vendor?->name ?? '-'),
            'currency_code' => strtoupper((string) ($receipt->order?->currency_code ?: 'NGN')),
            'order_total' => (int) ($receipt->order?->total_amount ?? 0),
            'line_count' => (int) $receipt->items->count(),
            'received_quantity_total' => (float) $receipt->items->sum('received_quantity'),
            'received_value_total' => (int) $receipt->items->sum('received_total'),
            'items' => $receipt->items->map(static function ($item): array {
                return [
                    'line_number' => (int) ($item->orderItem?->line_number ?? 0),
                    'description' => (string) ($item->orderItem?->item_description ?? 'Order line'),
                    'ordered_quantity' => (float) ($item->orderItem?->quantity ?? 0),
                    'received_quantity' => (float) ($item->received_quantity ?? 0),
                    'received_unit_cost' => (int) ($item->received_unit_cost ?? 0),
                    'received_total' => (int) ($item->received_total ?? 0),
                ];
            })->values()->all(),
            'linked_invoices' => $receipt->order?->vendorInvoices
                ?->sortByDesc('invoice_date')
                ->values()
                ->map(static function ($invoice): array {
                    return [
                        'invoice_number' => (string) $invoice->invoice_number,
                        'invoice_date' => optional($invoice->invoice_date)->format('M d, Y'),
                        'status' => (string) $invoice->status,
                        'currency' => strtoupper((string) ($invoice->currency ?: 'NGN')),
                        'total_amount' => (int) $invoice->total_amount,
                        'outstanding_amount' => (int) $invoice->outstanding_amount,
                    ];
                })->all() ?? [],
        ];

        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->selectedReceiptId = null;
        $this->selectedReceipt = null;
    }

    public function render(): View
    {
        $query = GoodsReceipt::query()
            ->with([
                'order:id,po_number,po_status,currency_code,vendor_id',
                'order.vendor:id,name',
                'receiver:id,name',
            ])
            ->withCount('items')
            ->when($this->search !== '', function ($builder): void {
                $builder->where(function ($inner): void {
                    $inner->where('receipt_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('po_number', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('order.vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('receipt_status', $this->statusFilter))
            ->when($this->receivedFrom, fn ($builder) => $builder->whereDate('received_at', '>=', $this->receivedFrom))
            ->when($this->receivedTo, fn ($builder) => $builder->whereDate('received_at', '<=', $this->receivedTo))
            ->latest('received_at');

        $receipts = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : GoodsReceipt::query()->whereRaw('1=0')->paginate($this->perPage);

        $summary = $this->readyToLoad
            ? [
                'total' => (clone $query)->count(),
                'confirmed' => (clone $query)->where('receipt_status', GoodsReceipt::STATUS_CONFIRMED)->count(),
                'value' => (int) GoodsReceiptItem::query()
                    ->whereIn('goods_receipt_id', (clone $query)->select('id'))
                    ->sum('received_total'),
            ]
            : ['total' => 0, 'confirmed' => 0, 'value' => 0];

        return view('livewire.procurement.purchase-receipts-page', [
            'receipts' => $receipts,
            'statuses' => GoodsReceipt::STATUSES,
            'summary' => $summary,
        ]);
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
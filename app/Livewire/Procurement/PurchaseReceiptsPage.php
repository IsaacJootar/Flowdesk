<?php

namespace App\Livewire\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $deepLinkSearch = trim((string) request()->query('search', ''));
        if ($deepLinkSearch !== '') {
            $this->search = mb_substr($deepLinkSearch, 0, 120);
        }

        $openReceiptId = (int) request()->query('open_receipt_id', 0);
        if ($openReceiptId > 0 && GoodsReceipt::query()->whereKey($openReceiptId)->exists()) {
            $this->readyToLoad = true;
            $this->openDetails($openReceiptId);
        }
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->resetPage();
    }

    public function updatedReceivedFrom(): void
    {
        $this->receivedFrom = $this->normalizeDateInput($this->receivedFrom);
        $this->normalizeReceivedDateRange();
        $this->resetPage();
    }

    public function updatedReceivedTo(): void
    {
        $this->receivedTo = $this->normalizeDateInput($this->receivedTo);
        $this->normalizeReceivedDateRange();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);

        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $this->normalizeFilterState();
        Gate::authorize('viewAny', GoodsReceipt::class);

        $fileName = 'procurement_receipts_'.now()->format('Ymd_His').'.csv';

        // Export uses the active page filters so finance teams can reconcile exactly what they reviewed.
        $query = $this->filteredReceiptsQuery()
            ->with([
                'order:id,po_number,po_status,currency_code,vendor_id',
                'order.vendor:id,name',
                'order.vendorInvoices:id,purchase_order_id',
                'receiver:id,name',
                'items:id,goods_receipt_id,received_quantity,received_total',
            ])
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            fputcsv($stream, [
                'Receipt Number',
                'Receipt Status',
                'Received At',
                'Receiver',
                'PO Number',
                'PO Status',
                'Vendor',
                'Currency',
                'Line Count',
                'Received Quantity Total',
                'Received Value Total',
                'Linked Invoice Count',
            ]);

            $query->chunkById(200, function ($receipts) use ($stream): void {
                foreach ($receipts as $receipt) {
                    fputcsv($stream, [
                        (string) $receipt->receipt_number,
                        (string) $receipt->receipt_status,
                        (string) ($receipt->received_at?->format('Y-m-d H:i:s') ?? ''),
                        (string) ($receipt->receiver?->name ?? '-'),
                        (string) ($receipt->order?->po_number ?? '-'),
                        (string) ($receipt->order?->po_status ?? '-'),
                        (string) ($receipt->order?->vendor?->name ?? '-'),
                        strtoupper((string) ($receipt->order?->currency_code ?: 'NGN')),
                        (string) $receipt->items->count(),
                        (string) number_format((float) $receipt->items->sum('received_quantity'), 2, '.', ''),
                        (string) number_format((int) $receipt->items->sum('received_total'), 2, '.', ''),
                        (string) ($receipt->order?->vendorInvoices?->count() ?? 0),
                    ]);
                }
            }, 'id');

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
        Gate::authorize('view', $receipt);

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
        $this->normalizeFilterState();

        $query = $this->filteredReceiptsQuery()
            ->with([
                'order:id,po_number,po_status,currency_code,vendor_id',
                'order.vendor:id,name',
                'receiver:id,name',
            ])
            ->withCount('items')
            ->latest('received_at')
            ->latest('id');

        $receipts = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : GoodsReceipt::query()->whereRaw('1=0')->paginate($this->perPage);

        $summaryQuery = $this->filteredReceiptsQuery();
        $summary = $this->readyToLoad
            ? [
                'total' => (clone $summaryQuery)->count(),
                'confirmed' => (clone $summaryQuery)->where('receipt_status', GoodsReceipt::STATUS_CONFIRMED)->count(),
                'value' => (int) GoodsReceiptItem::query()
                    ->whereIn('goods_receipt_id', (clone $summaryQuery)->select('id'))
                    ->sum('received_total'),
            ]
            : ['total' => 0, 'confirmed' => 0, 'value' => 0];

        return view('livewire.procurement.purchase-receipts-page', [
            'receipts' => $receipts,
            'statuses' => GoodsReceipt::STATUSES,
            'summary' => $summary,
        ]);
    }

    private function filteredReceiptsQuery(): Builder
    {
        return GoodsReceipt::query()
            ->when($this->search !== '', function ($builder): void {
                $builder->where(function ($inner): void {
                    $inner->where('receipt_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('po_number', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('order.vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('receipt_status', $this->statusFilter))
            ->when($this->receivedFrom, fn ($builder) => $builder->whereDate('received_at', '>=', $this->receivedFrom))
            ->when($this->receivedTo, fn ($builder) => $builder->whereDate('received_at', '<=', $this->receivedTo));
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', GoodsReceipt::class);
    }

    private function normalizeFilterState(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->receivedFrom = $this->normalizeDateInput($this->receivedFrom);
        $this->receivedTo = $this->normalizeDateInput($this->receivedTo);
        $this->normalizeReceivedDateRange();
        $this->perPage = $this->normalizePerPage($this->perPage);
    }

    private function normalizeSearch(string $value): string
    {
        return mb_substr(trim($value), 0, 120);
    }

    private function normalizeStatusFilter(string $value): string
    {
        $normalized = strtolower(trim($value));

        return $normalized === 'all' || in_array($normalized, GoodsReceipt::STATUSES, true)
            ? $normalized
            : 'all';
    }

    private function normalizeDateInput(?string $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasWarnings = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if (! $parsed instanceof \DateTimeImmutable || $hasWarnings) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    private function normalizeReceivedDateRange(): void
    {
        if ($this->receivedFrom !== null && $this->receivedTo !== null && $this->receivedFrom > $this->receivedTo) {
            $this->receivedTo = null;
        }
    }

    private function normalizePerPage(int $value): int
    {
        return in_array($value, [10, 25, 50], true) ? $value : 10;
    }
}

<?php

namespace App\Livewire\Procurement;

use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Order Workspace')]
class ProcurementReleaseDeskPage extends Component
{
    private const LANE_LIMIT = 8;

    /**
     * @var array<int, string>
     */
    private const MATCH_PASS_STATUSES = [
        InvoiceMatchResult::STATUS_MATCHED,
        InvoiceMatchResult::STATUS_OVERRIDDEN,
    ];

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function render(): View
    {
        return view('livewire.procurement.procurement-release-desk-page', [
            'summary' => $this->workspaceSummary(),
            'approvedRequestsLane' => $this->approvedRequestsNeedPoLane(),
            'poDraftsLane' => $this->poDraftsNeedIssueLane(),
            'issuedReceiptLane' => $this->issuedOrdersNeedReceiptLane(),
            'invoiceResolveLane' => $this->invoiceAndMatchResolveLane(),
            'readyPayoutLane' => $this->readyForPayoutLane(),
        ]);
    }

    /**
     * @return array{
     *   approved_need_po:int,
     *   po_drafts_need_issue:int,
     *   issued_need_receipt:int,
     *   invoice_match_resolve:int,
     *   ready_for_payout:int,
     *   workload_total:int,
     *   bottleneck_label:string,
     *   bottleneck_count:int,
     *   segments:array<int, array{key:string,label:string,count:int,percent:float,tone:string}>
     * }
     */
    private function workspaceSummary(): array
    {
        $approvedNeedPo = $this->countApprovedRequestsNeedPo();
        $poDraftsNeedIssue = $this->countPoDraftsNeedIssue();
        $issuedNeedReceipt = $this->countIssuedOrdersNeedReceipt();
        $invoiceMatchResolve = $this->countInvoiceAndMatchResolve();
        $readyForPayout = $this->countReadyForPayout();

        $workloadTotal = $approvedNeedPo + $poDraftsNeedIssue + $issuedNeedReceipt + $invoiceMatchResolve;

        $segments = [
            [
                'key' => 'approved_need_po',
                'label' => 'No PO Yet',
                'count' => $approvedNeedPo,
                'percent' => $workloadTotal > 0 ? round(($approvedNeedPo / $workloadTotal) * 100, 1) : 0.0,
                'tone' => 'amber',
            ],
            [
                'key' => 'po_drafts_need_issue',
                'label' => 'Not Sent to Vendor',
                'count' => $poDraftsNeedIssue,
                'percent' => $workloadTotal > 0 ? round(($poDraftsNeedIssue / $workloadTotal) * 100, 1) : 0.0,
                'tone' => 'indigo',
            ],
            [
                'key' => 'issued_need_receipt',
                'label' => 'Awaiting Delivery',
                'count' => $issuedNeedReceipt,
                'percent' => $workloadTotal > 0 ? round(($issuedNeedReceipt / $workloadTotal) * 100, 1) : 0.0,
                'tone' => 'sky',
            ],
            [
                'key' => 'invoice_match_resolve',
                'label' => 'Invoice Mismatch',
                'count' => $invoiceMatchResolve,
                'percent' => $workloadTotal > 0 ? round(($invoiceMatchResolve / $workloadTotal) * 100, 1) : 0.0,
                'tone' => 'rose',
            ],
        ];

        $bottleneckLabel = 'No blockers';
        $bottleneckCount = 0;

        foreach ($segments as $segment) {
            if ($segment['count'] > $bottleneckCount) {
                $bottleneckCount = (int) $segment['count'];
                $bottleneckLabel = (string) $segment['label'];
            }
        }

        return [
            'approved_need_po' => $approvedNeedPo,
            'po_drafts_need_issue' => $poDraftsNeedIssue,
            'issued_need_receipt' => $issuedNeedReceipt,
            'invoice_match_resolve' => $invoiceMatchResolve,
            'ready_for_payout' => $readyForPayout,
            'workload_total' => $workloadTotal,
            'bottleneck_label' => $bottleneckLabel,
            'bottleneck_count' => $bottleneckCount,
            'segments' => $segments,
        ];
    }

    /**
     * @return array<int, array{ref:string,title:string,meta:string,status:string,next_action_label:string,next_action_url:string,next_action_tone:string}>
     */
    private function approvedRequestsNeedPoLane(): array
    {
        $rows = SpendRequest::query()
            ->where('company_id', $this->companyId())
            ->whereIn('status', ['approved', 'approved_for_execution'])
            ->doesntHave('purchaseOrders')
            ->with([
                'requester:id,name',
                'department:id,name',
            ])
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->where('request_code', 'like', '%'.$this->search.'%')
                        ->orWhere('title', 'like', '%'.$this->search.'%');
                });
            })
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'company_id', 'request_code', 'title', 'status', 'requested_by', 'department_id', 'amount', 'approved_amount', 'currency']);

        return $rows->map(function (SpendRequest $request): array {
            $amount = (int) ($request->approved_amount ?: $request->amount);

            return [
                'ref' => (string) $request->request_code,
                'title' => (string) $request->title,
                'meta' => sprintf(
                    '%s | %s | %s %s',
                    (string) ($request->requester?->name ?? '-'),
                    (string) ($request->department?->name ?? '-'),
                    strtoupper((string) ($request->currency ?: 'NGN')),
                    number_format($amount)
                ),
                'status' => 'Approved — No PO Yet',
                'next_action_label' => 'Create Purchase Order',
                'next_action_url' => route('requests.index', ['open_request_id' => (int) $request->id]),
                'next_action_tone' => 'amber',
            ];
        })->all();
    }

    /**
     * @return array<int, array{ref:string,title:string,meta:string,status:string,next_action_label:string,next_action_url:string,next_action_tone:string}>
     */
    private function poDraftsNeedIssueLane(): array
    {
        $rows = PurchaseOrder::query()
            ->where('company_id', $this->companyId())
            ->where('po_status', PurchaseOrder::STATUS_DRAFT)
            ->with([
                'request:id,company_id,request_code,title',
                'vendor:id,name',
            ])
            ->when($this->search !== '', fn (Builder $query) => $this->applyOrderSearch($query))
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'company_id', 'spend_request_id', 'vendor_id', 'po_number', 'po_status', 'total_amount', 'currency_code']);

        return $rows
            ->filter(fn (PurchaseOrder $order): bool => $this->orderInTenantScope($order))
            ->map(function (PurchaseOrder $order): array {
                return [
                    'ref' => (string) $order->po_number,
                    'title' => (string) ($order->request?->title ?? 'Draft purchase order'),
                    'meta' => sprintf(
                        '%s | %s %s',
                        (string) ($order->vendor?->name ?? '-'),
                        strtoupper((string) ($order->currency_code ?: 'NGN')),
                        number_format((int) $order->total_amount)
                    ),
                    'status' => 'Draft — Not Sent to Vendor',
                    'next_action_label' => 'Send to Vendor',
                    'next_action_url' => route('procurement.orders', ['search' => (string) $order->po_number]),
                    'next_action_tone' => 'indigo',
                ];
            })->values()->all();
    }

    /**
     * @return array<int, array{ref:string,title:string,meta:string,status:string,next_action_label:string,next_action_url:string,next_action_tone:string}>
     */
    private function issuedOrdersNeedReceiptLane(): array
    {
        $rows = PurchaseOrder::query()
            ->where('company_id', $this->companyId())
            ->whereIn('po_status', [PurchaseOrder::STATUS_ISSUED, PurchaseOrder::STATUS_PART_RECEIVED])
            ->whereHas('items', function (Builder $query): void {
                $query->whereColumn('received_quantity', '<', 'quantity');
            })
            ->with([
                'request:id,company_id,request_code,title',
                'vendor:id,name',
            ])
            ->withCount('receipts')
            ->when($this->search !== '', fn (Builder $query) => $this->applyOrderSearch($query))
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'company_id', 'spend_request_id', 'vendor_id', 'po_number', 'po_status']);

        return $rows
            ->filter(fn (PurchaseOrder $order): bool => $this->orderInTenantScope($order))
            ->map(function (PurchaseOrder $order): array {
                $receiptCount = (int) ($order->receipts_count ?? 0);

                return [
                    'ref' => (string) $order->po_number,
                    'title' => (string) ($order->request?->title ?? 'Issued purchase order'),
                    'meta' => sprintf('%s | Receipts logged: %d', (string) ($order->vendor?->name ?? '-'), $receiptCount),
                    'status' => 'Waiting for Delivery',
                    'next_action_label' => 'Confirm Goods Received',
                    'next_action_url' => route('procurement.orders', ['search' => (string) $order->po_number]),
                    'next_action_tone' => 'sky',
                ];
            })->values()->all();
    }

    /**
     * @return array<int, array{ref:string,title:string,meta:string,status:string,next_action_label:string,next_action_url:string,next_action_tone:string}>
     */
    private function invoiceAndMatchResolveLane(): array
    {
        $rows = $this->invoiceAndPayoutCandidates(applySearch: true, limit: self::LANE_LIMIT * 8);

        $result = [];

        foreach ($rows as $order) {
            if (! $this->needsInvoiceOrMatchResolution($order)) {
                continue;
            }

            $status = $this->invoiceResolutionStatusLabel($order);
            $isInvoiceLinkingStep = $status === 'Waiting for Invoice';
            $openExceptionCount = (int) ($order->open_match_exceptions_count ?? 0);

            $result[] = [
                'ref' => (string) $order->po_number,
                'title' => (string) ($order->request?->title ?? 'Invoice or match follow-up'),
                'meta' => sprintf(
                    '%s | Invoices: %d | Mismatches: %d',
                    (string) ($order->vendor?->name ?? '-'),
                    (int) ($order->vendor_invoices_count ?? 0),
                    $openExceptionCount
                ),
                'status' => $status,
                'next_action_label' => $isInvoiceLinkingStep ? 'Attach Invoice' : 'Fix Mismatch',
                'next_action_url' => $isInvoiceLinkingStep
                    ? route('procurement.orders', ['search' => (string) $order->po_number])
                    : route('procurement.match-exceptions', ['search' => (string) $order->po_number]),
                'next_action_tone' => $isInvoiceLinkingStep ? 'emerald' : 'rose',
            ];

            if (count($result) >= self::LANE_LIMIT) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{ref:string,title:string,meta:string,status:string,next_action_label:string,next_action_url:string,next_action_tone:string}>
     */
    private function readyForPayoutLane(): array
    {
        $rows = $this->invoiceAndPayoutCandidates(applySearch: true, limit: self::LANE_LIMIT * 8);

        $result = [];

        foreach ($rows as $order) {
            if (! $this->isReadyForPayoutHandoff($order)) {
                continue;
            }

            $requestCode = (string) ($order->request?->request_code ?? '-');

            $result[] = [
                'ref' => (string) $order->po_number,
                'title' => (string) ($order->request?->title ?? 'Ready for payment'),
                'meta' => sprintf(
                    'Request: %s | %s | %s %s',
                    $requestCode,
                    (string) ($order->vendor?->name ?? '-'),
                    strtoupper((string) ($order->currency_code ?: 'NGN')),
                    number_format((int) $order->total_amount)
                ),
                'status' => 'Ready to Pay',
                'next_action_label' => 'Send Payment',
                'next_action_url' => route('execution.payout-ready', ['search' => $requestCode]),
                'next_action_tone' => 'slate',
            ];

            if (count($result) >= self::LANE_LIMIT) {
                break;
            }
        }

        return $result;
    }

    private function countApprovedRequestsNeedPo(): int
    {
        return (int) SpendRequest::query()
            ->where('company_id', $this->companyId())
            ->whereIn('status', ['approved', 'approved_for_execution'])
            ->doesntHave('purchaseOrders')
            ->count();
    }

    private function countPoDraftsNeedIssue(): int
    {
        return (int) PurchaseOrder::query()
            ->where('company_id', $this->companyId())
            ->where('po_status', PurchaseOrder::STATUS_DRAFT)
            ->count();
    }

    private function countIssuedOrdersNeedReceipt(): int
    {
        return (int) PurchaseOrder::query()
            ->where('company_id', $this->companyId())
            ->whereIn('po_status', [PurchaseOrder::STATUS_ISSUED, PurchaseOrder::STATUS_PART_RECEIVED])
            ->whereHas('items', function (Builder $query): void {
                $query->whereColumn('received_quantity', '<', 'quantity');
            })
            ->count();
    }

    private function countInvoiceAndMatchResolve(): int
    {
        return $this->invoiceAndPayoutCandidates(applySearch: false)
            ->filter(fn (PurchaseOrder $order): bool => $this->needsInvoiceOrMatchResolution($order))
            ->count();
    }

    private function countReadyForPayout(): int
    {
        return $this->invoiceAndPayoutCandidates(applySearch: false)
            ->filter(fn (PurchaseOrder $order): bool => $this->isReadyForPayoutHandoff($order))
            ->count();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    private function invoiceAndPayoutCandidates(bool $applySearch, ?int $limit = null): Collection
    {
        $query = PurchaseOrder::query()
            ->where('company_id', $this->companyId())
            ->whereIn('po_status', [
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_PART_RECEIVED,
                PurchaseOrder::STATUS_RECEIVED,
                PurchaseOrder::STATUS_INVOICED,
            ])
            ->with([
                'request:id,company_id,request_code,title',
                'vendor:id,name',
                'matchResults:id,company_id,purchase_order_id,vendor_invoice_id,match_status',
            ])
            ->withCount([
                'vendorInvoices',
                'matchResults',
                'matchExceptions as open_match_exceptions_count' => function (Builder $builder): void {
                    $builder->where('exception_status', InvoiceMatchException::STATUS_OPEN);
                },
            ])
            ->latest('id');

        if ($applySearch && $this->search !== '') {
            $this->applyOrderSearch($query);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get([
            'id',
            'company_id',
            'spend_request_id',
            'vendor_id',
            'po_number',
            'po_status',
            'currency_code',
            'total_amount',
        ])
            ->filter(fn (PurchaseOrder $order): bool => $this->orderInTenantScope($order))
            ->values();
    }

    private function needsInvoiceOrMatchResolution(PurchaseOrder $order): bool
    {
        if (! $this->orderInTenantScope($order)) {
            return false;
        }

        $invoiceCount = (int) ($order->vendor_invoices_count ?? 0);
        $matchResultCount = (int) ($order->match_results_count ?? 0);
        $openExceptions = (int) ($order->open_match_exceptions_count ?? 0);

        if ($invoiceCount === 0) {
            return true;
        }

        if ($openExceptions > 0) {
            return true;
        }

        if ($matchResultCount < $invoiceCount) {
            return true;
        }

        foreach ($order->matchResults as $result) {
            if (! in_array((string) $result->match_status, self::MATCH_PASS_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    private function isReadyForPayoutHandoff(PurchaseOrder $order): bool
    {
        if (! $this->orderInTenantScope($order)) {
            return false;
        }

        $invoiceCount = (int) ($order->vendor_invoices_count ?? 0);
        $matchResultCount = (int) ($order->match_results_count ?? 0);
        $openExceptions = (int) ($order->open_match_exceptions_count ?? 0);

        if ($invoiceCount === 0 || $matchResultCount === 0) {
            return false;
        }

        if ($openExceptions > 0) {
            return false;
        }

        if ($matchResultCount < $invoiceCount) {
            return false;
        }

        foreach ($order->matchResults as $result) {
            if (! in_array((string) $result->match_status, self::MATCH_PASS_STATUSES, true)) {
                return false;
            }
        }

        return true;
    }

    private function invoiceResolutionStatusLabel(PurchaseOrder $order): string
    {
        $invoiceCount = (int) ($order->vendor_invoices_count ?? 0);
        $matchResultCount = (int) ($order->match_results_count ?? 0);
        $openExceptions = (int) ($order->open_match_exceptions_count ?? 0);

        if ($invoiceCount === 0) {
            return 'Invoice Not Attached';
        }

        if ($openExceptions > 0) {
            return 'Invoice Mismatch';
        }

        if ($matchResultCount < $invoiceCount) {
            return 'Invoice Mismatch';
        }

        foreach ($order->matchResults as $result) {
            if (! in_array((string) $result->match_status, self::MATCH_PASS_STATUSES, true)) {
                return 'Invoice Mismatch';
            }
        }

        return 'Waiting for Match Review';
    }

    private function applyOrderSearch(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->where('po_number', 'like', '%'.$this->search.'%')
                ->orWhereHas('request', function (Builder $requestQuery): void {
                    $requestQuery->where('request_code', 'like', '%'.$this->search.'%')
                        ->orWhere('title', 'like', '%'.$this->search.'%');
                })
                ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
        });
    }

    private function orderInTenantScope(PurchaseOrder $order): bool
    {
        $currentCompanyId = $this->companyId();
        if ($currentCompanyId <= 0) {
            return false;
        }

        if ((int) ($order->company_id ?? 0) !== $currentCompanyId) {
            Log::error('Procurement workspace row dropped due to purchase order scope mismatch.', [
                'purchase_order_id' => (int) $order->id,
                'order_company_id' => (int) ($order->company_id ?? 0),
                'viewer_company_id' => $currentCompanyId,
            ]);

            return false;
        }

        $requestCompanyId = (int) ($order->request?->company_id ?? 0);
        if ($order->request && $requestCompanyId <= 0) {
            Log::error('Procurement workspace row dropped due to missing linked request company scope.', [
                'purchase_order_id' => (int) $order->id,
                'viewer_company_id' => $currentCompanyId,
            ]);

            return false;
        }

        if ($requestCompanyId > 0 && $requestCompanyId !== $currentCompanyId) {
            Log::error('Procurement workspace row dropped due to linked request scope mismatch.', [
                'purchase_order_id' => (int) $order->id,
                'request_company_id' => $requestCompanyId,
                'viewer_company_id' => $currentCompanyId,
            ]);

            return false;
        }

        return true;
    }

    private function companyId(): int
    {
        return (int) (auth()->user()?->company_id ?? 0);
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', PurchaseOrder::class);
    }
}

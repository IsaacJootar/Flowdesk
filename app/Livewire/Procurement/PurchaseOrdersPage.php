<?php

namespace App\Livewire\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Enums\UserRole;
use App\Models\User;
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
            ->with(['request:id,request_code,title,status', 'vendor:id,name', 'items', 'commitments'])
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
        $this->fillSelectedOrder($issued);
        $this->resetPage();
    }

    public function render(ProcurementControlSettingsService $settingsService): View
    {
        $query = PurchaseOrder::query()
            ->with(['request:id,request_code,title,status', 'vendor:id,name'])
            ->withCount(['items', 'commitments'])
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
            ]
            : ['total' => 0, 'draft' => 0, 'issued' => 0];

        return view('livewire.procurement.purchase-orders-page', [
            'orders' => $orders,
            'statuses' => PurchaseOrder::STATUSES,
            'summary' => $summary,
            'issueRoles' => (array) $settingsService->effectiveControls((int) auth()->user()->company_id)['issue_allowed_roles'],
        ]);
    }

    private function fillSelectedOrder(PurchaseOrder $order): void
    {
        $order = $order->loadMissing(['request:id,request_code,title,status', 'vendor:id,name', 'items', 'commitments']);

        $controls = app(ProcurementControlSettingsService::class)->effectiveControls((int) auth()->user()->company_id);
        $canIssue = in_array((string) auth()->user()->role, (array) ($controls['issue_allowed_roles'] ?? []), true)
            && (string) $order->po_status === PurchaseOrder::STATUS_DRAFT;

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
            'can_issue' => $canIssue,
            'items' => $order->items->map(fn ($item): array => [
                'line_number' => (int) $item->line_number,
                'item_description' => (string) $item->item_description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (int) $item->unit_price,
                'line_total' => (int) $item->line_total,
            ])->values()->all(),
            'commitments' => $order->commitments->map(fn ($commitment): array => [
                'status' => (string) $commitment->commitment_status,
                'amount' => (int) $commitment->amount,
                'effective_at' => optional($commitment->effective_at)->format('M d, Y H:i'),
            ])->values()->all(),
        ];
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

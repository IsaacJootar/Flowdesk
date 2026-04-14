<?php

namespace App\Livewire\Treasury;

use App\Domains\Treasury\Models\PaymentRun;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payment Runs')]
class TreasuryPaymentRunsPage extends Component
{
    use WithPagination;

    private const ALLOWED_PER_PAGE = [10, 25, 50];

    private const ALLOWED_STATUS_FILTERS = ['all', 'draft', 'processing', 'completed', 'failed', 'canceled'];

    private const ALLOWED_TYPE_FILTERS = ['all', 'mixed', 'payout', 'vendor', 'reimbursement'];

    public bool $readyToLoad = false;

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public int $perPage = 10;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
        $this->normalizeFilters();
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->typeFilter = $this->normalizeTypeFilter($this->typeFilter);
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);

        $this->resetPage();
    }

    public function render(): View
    {
        $this->normalizeFilters();

        $query = PaymentRun::query()
            ->where('company_id', (int) auth()->user()->company_id)
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('run_status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn ($builder) => $builder->where('run_type', $this->typeFilter))
            ->latest('id');

        $runs = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : PaymentRun::query()->whereRaw('1=0')->paginate($this->perPage);

        $summary = $this->readyToLoad
            ? [
                'total' => (clone $query)->count(),
                'processing' => (clone $query)->where('run_status', 'processing')->count(),
                'completed' => (clone $query)->where('run_status', 'completed')->count(),
            ]
            : ['total' => 0, 'processing' => 0, 'completed' => 0];

        return view('livewire.treasury.treasury-payment-runs-page', [
            'runs' => $runs,
            'summary' => $summary,
            'statuses' => ['all', 'draft', 'processing', 'completed', 'failed', 'canceled'],
            'types' => ['all', 'mixed', 'payout', 'vendor', 'reimbursement'],
        ]);
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', PaymentRun::class);
    }

    private function normalizeFilters(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->typeFilter = $this->normalizeTypeFilter($this->typeFilter);
        $this->perPage = $this->normalizePerPage($this->perPage);
    }

    private function normalizeStatusFilter(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, self::ALLOWED_STATUS_FILTERS, true)
            ? $normalized
            : 'all';
    }

    private function normalizeTypeFilter(string $type): string
    {
        $normalized = strtolower(trim($type));

        return in_array($normalized, self::ALLOWED_TYPE_FILTERS, true)
            ? $normalized
            : 'all';
    }

    private function normalizePerPage(int $perPage): int
    {
        return in_array($perPage, self::ALLOWED_PER_PAGE, true)
            ? $perPage
            : self::ALLOWED_PER_PAGE[0];
    }
}

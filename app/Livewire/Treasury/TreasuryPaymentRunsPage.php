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
#[Title('Treasury Payment Runs')]
class TreasuryPaymentRunsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public int $perPage = 10;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
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

    public function render(): View
    {
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
}

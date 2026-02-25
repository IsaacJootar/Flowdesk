<?php

namespace App\Livewire\Vendors;

use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Vendor Reports')]
class VendorReportsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $vendorLinkFilter = 'all';

    public string $requestLinkFilter = 'all';

    public string $statusFilter = 'all';

    public string $departmentFilter = 'all';

    public string $vendorFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $amountMin = '';

    public string $amountMax = '';

    public int $perPage = 10;

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', Vendor::class), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedVendorLinkFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRequestLinkFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedVendorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedAmountMin(): void
    {
        $this->resetPage();
    }

    public function updatedAmountMax(): void
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
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $vendors = Vendor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $baseQuery = $this->baseQuery();
        $metrics = $this->buildMetrics(clone $baseQuery);
        $agingMetrics = $this->buildAgingMetrics();

        $expenses = $this->readyToLoad
            ? (clone $baseQuery)
                ->with([
                    'department:id,name',
                    'vendor:id,name',
                    // Include soft-deleted requests so trace links still work for historical rows.
                    'request' => fn ($query) => $query->withTrashed()->select(['id', 'request_code', 'title', 'status']),
                    'creator:id,name',
                ])
                ->latest('expense_date')
                ->latest('id')
                ->paginate($this->perPage)
            : Expense::query()->whereRaw('1 = 0')->paginate($this->perPage);

        return view('livewire.vendors.vendor-reports-page', [
            'expenses' => $expenses,
            'departments' => $departments,
            'vendors' => $vendors,
            'statuses' => ['posted', 'void'],
            'metrics' => $metrics,
            'agingMetrics' => $agingMetrics,
        ]);
    }

    private function baseQuery(): Builder
    {
        $query = Expense::query();

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('expense_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('request', fn (Builder $requestQuery) => $requestQuery->where('request_code', 'like', '%'.$search.'%'));
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('department_id', (int) $this->departmentFilter);
        }

        if ($this->vendorFilter !== 'all') {
            $query->where('vendor_id', (int) $this->vendorFilter);
        }

        if ($this->vendorLinkFilter === 'linked') {
            $query->whereNotNull('vendor_id');
        } elseif ($this->vendorLinkFilter === 'unlinked') {
            $query->whereNull('vendor_id');
        }

        if ($this->requestLinkFilter === 'linked') {
            $query->whereNotNull('request_id');
        } elseif ($this->requestLinkFilter === 'unlinked') {
            $query->whereNull('request_id');
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('expense_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('expense_date', '<=', $this->dateTo);
        }

        if ($this->amountMin !== '' && is_numeric($this->amountMin)) {
            $query->where('amount', '>=', (int) $this->amountMin);
        }

        if ($this->amountMax !== '' && is_numeric($this->amountMax)) {
            $query->where('amount', '<=', (int) $this->amountMax);
        }

        return $this->applyRoleScope($query);
    }

    private function applyRoleScope(Builder $query): Builder
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        if ((string) $user->role === UserRole::Manager->value) {
            // Managers operate on department scope, plus their own authored expenses.
            return $query->where(function (Builder $builder) use ($user): void {
                if ($user->department_id) {
                    $builder->where('department_id', (int) $user->department_id)
                        ->orWhere('created_by', (int) $user->id);
                } else {
                    $builder->where('created_by', (int) $user->id);
                }
            });
        }

        return $query->where('created_by', (int) $user->id);
    }

    /**
     * @return array{
     *   total_count:int,
     *   total_amount:int,
     *   vendor_linked_count:int,
     *   vendor_linked_amount:int,
     *   vendor_unlinked_count:int,
     *   vendor_unlinked_amount:int,
     *   request_linked_count:int,
     *   request_linked_amount:int,
     *   fully_linked_count:int
     * }
     */
    private function buildMetrics(Builder $query): array
    {
        $totalCount = (clone $query)->count();
        $totalAmount = (int) ((clone $query)->sum('amount') ?? 0);

        $vendorLinkedCount = (clone $query)->whereNotNull('vendor_id')->count();
        $vendorLinkedAmount = (int) ((clone $query)->whereNotNull('vendor_id')->sum('amount') ?? 0);

        $vendorUnlinkedCount = (clone $query)->whereNull('vendor_id')->count();
        $vendorUnlinkedAmount = (int) ((clone $query)->whereNull('vendor_id')->sum('amount') ?? 0);

        $requestLinkedCount = (clone $query)->whereNotNull('request_id')->count();
        $requestLinkedAmount = (int) ((clone $query)->whereNotNull('request_id')->sum('amount') ?? 0);

        $fullyLinkedCount = (clone $query)
            ->whereNotNull('request_id')
            ->whereNotNull('vendor_id')
            ->count();

        return [
            'total_count' => $totalCount,
            'total_amount' => $totalAmount,
            'vendor_linked_count' => $vendorLinkedCount,
            'vendor_linked_amount' => $vendorLinkedAmount,
            'vendor_unlinked_count' => $vendorUnlinkedCount,
            'vendor_unlinked_amount' => $vendorUnlinkedAmount,
            'request_linked_count' => $requestLinkedCount,
            'request_linked_amount' => $requestLinkedAmount,
            'fully_linked_count' => $fullyLinkedCount,
        ];
    }

    /**
     * @return array{
     *   outstanding_count:int,
     *   outstanding_amount:int,
     *   overdue_0_30_count:int,
     *   overdue_0_30_amount:int,
     *   overdue_31_60_count:int,
     *   overdue_31_60_amount:int,
     *   overdue_61_plus_count:int,
     *   overdue_61_plus_amount:int
     * }
     */
    private function buildAgingMetrics(): array
    {
        $today = Carbon::now()->startOfDay();
        $day30 = $today->copy()->subDays(30);
        $day60 = $today->copy()->subDays(60);

        $query = $this->baseAgingQuery();

        $outstandingCount = (clone $query)->count();
        $outstandingAmount = (int) ((clone $query)->sum('outstanding_amount') ?? 0);

        $overdue0to30Query = (clone $query)
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereDate('due_date', '>=', $day30->toDateString());
        $overdue31to60Query = (clone $query)
            ->whereDate('due_date', '<', $day30->toDateString())
            ->whereDate('due_date', '>=', $day60->toDateString());
        $overdue61PlusQuery = (clone $query)
            ->whereDate('due_date', '<', $day60->toDateString());

        return [
            'outstanding_count' => $outstandingCount,
            'outstanding_amount' => $outstandingAmount,
            'overdue_0_30_count' => (clone $overdue0to30Query)->count(),
            'overdue_0_30_amount' => (int) ((clone $overdue0to30Query)->sum('outstanding_amount') ?? 0),
            'overdue_31_60_count' => (clone $overdue31to60Query)->count(),
            'overdue_31_60_amount' => (int) ((clone $overdue31to60Query)->sum('outstanding_amount') ?? 0),
            'overdue_61_plus_count' => (clone $overdue61PlusQuery)->count(),
            'overdue_61_plus_amount' => (int) ((clone $overdue61PlusQuery)->sum('outstanding_amount') ?? 0),
        ];
    }

    private function baseAgingQuery(): Builder
    {
        $query = VendorInvoice::query()
            ->where('status', '!=', VendorInvoice::STATUS_VOID)
            ->where('outstanding_amount', '>', 0);

        if ($this->vendorFilter !== 'all') {
            $query->where('vendor_id', (int) $this->vendorFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('due_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('due_date', '<=', $this->dateTo);
        }

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query;
    }
}


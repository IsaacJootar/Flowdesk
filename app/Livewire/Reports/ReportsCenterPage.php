<?php

namespace App\Livewire\Reports;

use App\Domains\Assets\Models\Asset;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Reports Center')]
class ReportsCenterPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $moduleFilter = 'all';

    public string $search = '';

    public string $departmentFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 10;

    public function mount(): void
    {
        abort_unless($this->canAccessCenter(), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedModuleFilter(): void
    {
        if (! in_array($this->moduleFilter, array_keys($this->moduleOptions()), true)) {
            $this->moduleFilter = 'all';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
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

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function render(): View
    {
        $departments = $this->readyToLoad
            ? Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $metrics = $this->readyToLoad
            ? $this->buildMetrics()
            : $this->emptyMetrics();
        $activities = $this->readyToLoad
            ? $this->buildUnifiedActivityFeed()
            : $this->emptyPaginator();

        return view('livewire.reports.reports-center-page', [
            'departments' => $departments,
            'moduleOptions' => $this->moduleOptions(),
            'metrics' => $metrics,
            'activities' => $activities,
            'quickLinks' => $this->quickLinks(),
            'canViewVendors' => $this->canViewVendors(),
            'currencyCode' => strtoupper((string) (Auth::user()?->company?->currency_code ?: 'NGN')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function moduleOptions(): array
    {
        $options = [
            'all' => 'All modules',
            'requests' => 'Requests',
            'expenses' => 'Expenses',
            'assets' => 'Assets',
            'budgets' => 'Budgets',
            'treasury' => 'Treasury',
        ];

        if ($this->canViewVendors()) {
            $options['vendors'] = 'Vendors';
        }

        return $options;
    }

    /**
     * @return array{
     *   requests: array{total:int, in_review:int, approved:int, amount:int},
     *   expenses: array{total:int, posted:int, void:int, amount:int},
     *   vendors: array{outstanding_count:int, outstanding_amount:int, overdue_count:int},
     *   assets: array{total:int, assigned:int, in_maintenance:int, disposed:int},
     *   budgets: array{active_count:int, allocated:int, used:int, remaining:int}
     * }
     */
    private function buildMetrics(): array
    {
        $requestQuery = $this->requestQuery();
        $expenseQuery = $this->expenseQuery();
        $assetQuery = $this->assetQuery();
        $budgetQuery = $this->budgetQuery();

        $requests = [
            'total' => (clone $requestQuery)->count(),
            'in_review' => (clone $requestQuery)->where('status', 'in_review')->count(),
            'approved' => (clone $requestQuery)->where('status', 'approved')->count(),
            'amount' => (int) ((clone $requestQuery)->sum('amount') ?? 0),
        ];

        $expenses = [
            'total' => (clone $expenseQuery)->count(),
            'posted' => (clone $expenseQuery)->where('status', 'posted')->count(),
            'void' => (clone $expenseQuery)->where('status', 'void')->count(),
            'amount' => (int) ((clone $expenseQuery)->where('status', 'posted')->sum('amount') ?? 0),
        ];

        $assets = [
            'total' => (clone $assetQuery)->count(),
            'assigned' => (clone $assetQuery)->whereNotNull('assigned_to_user_id')->count(),
            'in_maintenance' => (clone $assetQuery)->where('status', Asset::STATUS_IN_MAINTENANCE)->count(),
            'disposed' => (clone $assetQuery)->where('status', Asset::STATUS_DISPOSED)->count(),
        ];

        $budgets = [
            'active_count' => (clone $budgetQuery)->where('status', 'active')->count(),
            'allocated' => (int) ((clone $budgetQuery)->sum('allocated_amount') ?? 0),
            'used' => (int) ((clone $budgetQuery)->sum('used_amount') ?? 0),
            'remaining' => (int) ((clone $budgetQuery)->sum('remaining_amount') ?? 0),
        ];

        // Reconciliation visibility keeps finance aware of close risk directly from Reports Center.
        $treasury = [
            'reconciled_lines' => BankStatementLine::query()
                ->where('company_id', (int) Auth::user()?->company_id)
                ->where('is_reconciled', true)
                ->count(),
            'open_exceptions' => ReconciliationException::query()
                ->where('company_id', (int) Auth::user()?->company_id)
                ->where('exception_status', ReconciliationException::STATUS_OPEN)
                ->count(),
            'unreconciled_value' => (int) (BankStatementLine::query()
                ->where('company_id', (int) Auth::user()?->company_id)
                ->where('is_reconciled', false)
                ->sum('amount') ?? 0),
        ];

        $vendors = [
            'outstanding_count' => 0,
            'outstanding_amount' => 0,
            'overdue_count' => 0,
        ];

        if ($this->canViewVendors()) {
            $vendorQuery = $this->vendorInvoiceQuery();
            $vendors = [
                'outstanding_count' => (clone $vendorQuery)->where('outstanding_amount', '>', 0)->count(),
                'outstanding_amount' => (int) ((clone $vendorQuery)->where('outstanding_amount', '>', 0)->sum('outstanding_amount') ?? 0),
                'overdue_count' => (clone $vendorQuery)
                    ->where('outstanding_amount', '>', 0)
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->count(),
            ];
        }

        return [
            'requests' => $requests,
            'expenses' => $expenses,
            'vendors' => $vendors,
            'assets' => $assets,
            'budgets' => $budgets,
            'treasury' => $treasury,
        ];
    }

    private function buildUnifiedActivityFeed(): LengthAwarePaginator
    {
        $events = collect();

        if ($this->isModuleVisible('requests')) {
            $events = $events->concat($this->requestEvents());
        }

        if ($this->isModuleVisible('expenses')) {
            $events = $events->concat($this->expenseEvents());
        }

        if ($this->canViewVendors() && $this->isModuleVisible('vendors')) {
            $events = $events->concat($this->vendorEvents());
        }

        if ($this->isModuleVisible('assets')) {
            $events = $events->concat($this->assetEvents());
        }

        if ($this->isModuleVisible('budgets')) {
            $events = $events->concat($this->budgetEvents());
        }

        if ($this->isModuleVisible('treasury')) {
            $events = $events->concat($this->treasuryEvents());
        }

        $sorted = $events
            ->sortByDesc(fn (array $event) => $event['occurred_at'])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $total = $sorted->count();
        $items = $sorted->forPage($page, $this->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $this->perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function requestEvents(): Collection
    {
        return $this->requestQuery()
            ->with(['requester:id,name', 'department:id,name'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (SpendRequest $request): array {
                return [
                    'module' => 'Requests',
                    'code' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'status' => (string) $request->status,
                    'amount' => (int) $request->amount,
                    'department' => $request->department?->name ?? '-',
                    'owner' => $request->requester?->name ?? '-',
                    'occurred_at' => $request->updated_at ?? $request->created_at,
                    'url' => route('requests.index', ['open_request_id' => $request->id]),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function expenseEvents(): Collection
    {
        return $this->expenseQuery()
            ->with(['creator:id,name', 'department:id,name'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (Expense $expense): array {
                return [
                    'module' => 'Expenses',
                    'code' => (string) $expense->expense_code,
                    'title' => (string) $expense->title,
                    'status' => (string) $expense->status,
                    'amount' => (int) $expense->amount,
                    'department' => $expense->department?->name ?? '-',
                    'owner' => $expense->creator?->name ?? '-',
                    'occurred_at' => $expense->updated_at ?? $expense->created_at,
                    'url' => route('expenses.index', ['search' => $expense->expense_code]),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function vendorEvents(): Collection
    {
        return $this->vendorInvoiceQuery()
            ->with(['vendor:id,name'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (VendorInvoice $invoice): array {
                return [
                    'module' => 'Vendors',
                    'code' => (string) $invoice->invoice_number,
                    'title' => (string) ($invoice->vendor?->name ?? 'Vendor invoice'),
                    'status' => (string) $invoice->status,
                    'amount' => (int) $invoice->outstanding_amount,
                    'department' => '-',
                    'owner' => '-',
                    'occurred_at' => $invoice->updated_at ?? $invoice->created_at,
                    'url' => route('vendors.show', ['vendor' => $invoice->vendor_id]),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assetEvents(): Collection
    {
        return $this->assetQuery()
            ->with(['assignee:id,name', 'assignedDepartment:id,name'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (Asset $asset): array {
                return [
                    'module' => 'Assets',
                    'code' => (string) $asset->asset_code,
                    'title' => (string) $asset->name,
                    'status' => (string) $asset->status,
                    'amount' => (int) ($asset->purchase_amount ?? 0),
                    'department' => $asset->assignedDepartment?->name ?? '-',
                    'owner' => $asset->assignee?->name ?? '-',
                    'occurred_at' => $asset->updated_at ?? $asset->created_at,
                    'url' => route('assets.index', ['search' => $asset->asset_code]),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function budgetEvents(): Collection
    {
        return $this->budgetQuery()
            ->with(['department:id,name'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (DepartmentBudget $budget): array {
                $period = trim(
                    (string) optional($budget->period_start)->format('M d, Y')
                    .' - '
                    .(string) optional($budget->period_end)->format('M d, Y')
                );

                return [
                    'module' => 'Budgets',
                    'code' => strtoupper((string) $budget->period_type),
                    'title' => $period === '-' ? 'Budget period' : $period,
                    'status' => (string) $budget->status,
                    'amount' => (int) $budget->remaining_amount,
                    'department' => $budget->department?->name ?? '-',
                    'owner' => '-',
                    'occurred_at' => $budget->updated_at ?? $budget->created_at,
                    'url' => route('budgets.index'),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function treasuryEvents(): Collection
    {
        return ReconciliationException::query()
            ->where('company_id', (int) Auth::user()?->company_id)
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (ReconciliationException $exception): array {
                return [
                    'module' => 'Treasury',
                    'code' => strtoupper((string) $exception->exception_code),
                    'title' => 'Reconciliation exception',
                    'status' => (string) $exception->exception_status,
                    'amount' => 0,
                    'department' => '-',
                    'owner' => '-',
                    'occurred_at' => $exception->updated_at ?? $exception->created_at,
                    'url' => route('treasury.reconciliation-exceptions'),
                ];
            });
    }

    private function requestQuery(): Builder
    {
        $query = SpendRequest::query()
            ->when($this->search !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('request_code', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhereHas('requester', fn (Builder $requester) => $requester->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($this->departmentFilter !== 'all', fn (Builder $builder) => $builder->where('department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', fn (Builder $builder) => $builder->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $builder) => $builder->whereDate('created_at', '<=', $this->dateTo));

        return $this->applyRequestRoleScope($query);
    }

    private function expenseQuery(): Builder
    {
        $query = Expense::query()
            ->when($this->search !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('expense_code', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%');
                });
            })
            ->when($this->departmentFilter !== 'all', fn (Builder $builder) => $builder->where('department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', fn (Builder $builder) => $builder->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $builder) => $builder->whereDate('expense_date', '<=', $this->dateTo));

        return $this->applyExpenseRoleScope($query);
    }

    private function vendorInvoiceQuery(): Builder
    {
        $query = VendorInvoice::query()
            ->where('status', '!=', VendorInvoice::STATUS_VOID)
            ->when($this->search !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($this->dateFrom !== '', fn (Builder $builder) => $builder->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $builder) => $builder->whereDate('invoice_date', '<=', $this->dateTo));

        return $this->applyVendorRoleScope($query);
    }

    private function assetQuery(): Builder
    {
        $query = Asset::query()
            ->when($this->search !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('asset_code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('serial_number', 'like', '%'.$search.'%');
                });
            })
            ->when($this->departmentFilter !== 'all', fn (Builder $builder) => $builder->where('assigned_department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', fn (Builder $builder) => $builder->whereDate('acquisition_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $builder) => $builder->whereDate('acquisition_date', '<=', $this->dateTo));

        return $this->applyAssetRoleScope($query);
    }

    private function budgetQuery(): Builder
    {
        $query = DepartmentBudget::query()
            ->when($this->search !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('period_type', 'like', '%'.$search.'%')
                        ->orWhereHas('department', fn (Builder $department) => $department->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($this->departmentFilter !== 'all', fn (Builder $builder) => $builder->where('department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', fn (Builder $builder) => $builder->whereDate('period_start', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $builder) => $builder->whereDate('period_end', '<=', $this->dateTo));

        return $this->applyBudgetRoleScope($query);
    }

    private function applyRequestRoleScope(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        // Managers keep department visibility while retaining their own records if moved across teams.
        return $query->where(function (Builder $builder) use ($user): void {
            if ($user->department_id) {
                $builder->where('department_id', (int) $user->department_id)
                    ->orWhere('requested_by', (int) $user->id);
            } else {
                $builder->where('requested_by', (int) $user->id);
            }
        });
    }

    private function applyExpenseRoleScope(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            if ($user->department_id) {
                $builder->where('department_id', (int) $user->department_id)
                    ->orWhere('created_by', (int) $user->id);
            } else {
                $builder->where('created_by', (int) $user->id);
            }
        });
    }

    private function applyAssetRoleScope(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            if ($user->department_id) {
                $builder->where('assigned_department_id', (int) $user->department_id)
                    ->orWhere('assigned_to_user_id', (int) $user->id)
                    ->orWhere('created_by', (int) $user->id);
            } else {
                $builder->where('assigned_to_user_id', (int) $user->id)
                    ->orWhere('created_by', (int) $user->id);
            }
        });
    }

    private function applyBudgetRoleScope(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        if ($user->department_id) {
            return $query->where('department_id', (int) $user->department_id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function applyVendorRoleScope(Builder $query): Builder
    {
        return $this->canViewVendors() ? $query : $query->whereRaw('1 = 0');
    }

    private function isModuleVisible(string $module): bool
    {
        return $this->moduleFilter === 'all' || $this->moduleFilter === $module;
    }

    /**
     * @return array<int, array{label:string, route:string}>
     */
    private function quickLinks(): array
    {
        $links = [
            ['label' => 'Request Reports', 'route' => route('requests.reports')],
            ['label' => 'Expenses', 'route' => route('expenses.index')],
            ['label' => 'Asset Reports', 'route' => route('assets.reports')],
            ['label' => 'Budgets', 'route' => route('budgets.index')],
        ];

        if ($this->canViewVendors()) {
            $links[] = ['label' => 'Vendor Reports', 'route' => route('vendors.reports')];
        }

        return $links;
    }

    private function canAccessCenter(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canViewVendors(): bool
    {
        $user = Auth::user();

        return (bool) ($user && Gate::forUser($user)->allows('viewAny', Vendor::class));
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $this->perPage,
            LengthAwarePaginator::resolveCurrentPage(),
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * @return array{
     *   requests: array{total:int, in_review:int, approved:int, amount:int},
     *   expenses: array{total:int, posted:int, void:int, amount:int},
     *   vendors: array{outstanding_count:int, outstanding_amount:int, overdue_count:int},
     *   assets: array{total:int, assigned:int, in_maintenance:int, disposed:int},
     *   budgets: array{active_count:int, allocated:int, used:int, remaining:int}
     * }
     */
    private function emptyMetrics(): array
    {
        return [
            'requests' => ['total' => 0, 'in_review' => 0, 'approved' => 0, 'amount' => 0],
            'expenses' => ['total' => 0, 'posted' => 0, 'void' => 0, 'amount' => 0],
            'vendors' => ['outstanding_count' => 0, 'outstanding_amount' => 0, 'overdue_count' => 0],
            'assets' => ['total' => 0, 'assigned' => 0, 'in_maintenance' => 0, 'disposed' => 0],
            'budgets' => ['active_count' => 0, 'allocated' => 0, 'used' => 0, 'remaining' => 0],
            'treasury' => ['reconciled_lines' => 0, 'open_exceptions' => 0, 'unreconciled_value' => 0],
        ];
    }
}

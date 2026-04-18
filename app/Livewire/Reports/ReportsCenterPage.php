<?php

namespace App\Livewire\Reports;

use App\Domains\Assets\Models\Asset;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantPilotWaveOutcome;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Services\Procurement\ProcurementControlSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Reports')]
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
                ->where('company_id', (int) Auth::user()?->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $metrics = $this->readyToLoad
            ? $this->cachedMetrics()
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
            'all' => 'All areas',
            'requests' => 'Requests',
            'expenses' => 'Expenses',
            'assets' => 'Assets',
            'budgets' => 'Budgets',
            'treasury' => 'Bank Reconciliation',
            'rollout' => 'Provider Rollout',
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
     *   procurement: array{linked_invoices:int, open_exceptions:int, match_pass_rate_percent:float, stale_commitments:int},
     *   budgets: array{active_count:int, allocated:int, used:int, remaining:int},
     *   treasury: array{reconciled_lines:int, open_exceptions:int, unreconciled_value:int},
     *   rollout: array{go:int, hold:int, no_go:int, total:int}
     * }
     */
    private function buildMetrics(): array
    {
        $requestQuery = $this->requestQuery();
        $expenseQuery = $this->expenseQuery();
        $assetQuery = $this->assetQuery();
        $budgetQuery = $this->budgetQuery();
        $companyId = (int) Auth::user()?->company_id;

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

        $matchResultsBaseQuery = InvoiceMatchResult::query()
            ->where('company_id', $companyId)
            ->whereIn('match_status', [
                InvoiceMatchResult::STATUS_MATCHED,
                InvoiceMatchResult::STATUS_MISMATCH,
                InvoiceMatchResult::STATUS_OVERRIDDEN,
            ]);
        $matchResultsTotal = (int) (clone $matchResultsBaseQuery)->count();
        $matchResultsPassed = (int) (clone $matchResultsBaseQuery)
            ->whereIn('match_status', [InvoiceMatchResult::STATUS_MATCHED, InvoiceMatchResult::STATUS_OVERRIDDEN])
            ->count();

        $procurementControls = app(ProcurementControlSettingsService::class)->effectiveControls($companyId);
        $staleCommitmentAgeHours = max(1, (int) ($procurementControls['stale_commitment_alert_age_hours'] ?? 72));
        // Use tenant-defined stale age so KPI cards match guardrail and alert semantics exactly.
        $staleCommitmentCutoff = Carbon::now()->subHours($staleCommitmentAgeHours);

        $procurement = [
            'linked_invoices' => (int) VendorInvoice::query()
                ->where('company_id', $companyId)
                ->whereNotNull('purchase_order_id')
                ->where('status', '!=', VendorInvoice::STATUS_VOID)
                ->count(),
            'open_exceptions' => (int) InvoiceMatchException::query()
                ->where('company_id', $companyId)
                ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                ->count(),
            'match_pass_rate_percent' => $matchResultsTotal > 0
                ? round(($matchResultsPassed / $matchResultsTotal) * 100, 1)
                : 0.0,
            'stale_commitments' => (int) ProcurementCommitment::query()
                ->where('company_id', $companyId)
                ->where('commitment_status', ProcurementCommitment::STATUS_ACTIVE)
                ->where('effective_at', '<=', $staleCommitmentCutoff)
                ->count(),
        ];

        // Reconciliation visibility keeps finance aware of close risk directly from Reports Center.
        $treasury = [
            'reconciled_lines' => BankStatementLine::query()
                ->where('company_id', $companyId)
                ->where('is_reconciled', true)
                ->count(),
            'open_exceptions' => ReconciliationException::query()
                ->where('company_id', $companyId)
                ->where('exception_status', ReconciliationException::STATUS_OPEN)
                ->count(),
            'unreconciled_value' => (int) (BankStatementLine::query()
                ->where('company_id', $companyId)
                ->where('is_reconciled', false)
                ->sum('amount') ?? 0),
        ];

        // Rollout outcome counts keep tenant leadership aligned on go/hold/no-go decision posture.
        $rolloutOutcomeQuery = $this->pilotWaveOutcomeQuery();
        $rollout = [
            'go' => (int) (clone $rolloutOutcomeQuery)->where('outcome', TenantPilotWaveOutcome::OUTCOME_GO)->count(),
            'hold' => (int) (clone $rolloutOutcomeQuery)->where('outcome', TenantPilotWaveOutcome::OUTCOME_HOLD)->count(),
            'no_go' => (int) (clone $rolloutOutcomeQuery)->where('outcome', TenantPilotWaveOutcome::OUTCOME_NO_GO)->count(),
            'total' => (int) (clone $rolloutOutcomeQuery)->count(),
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
            'procurement' => $procurement,
            'budgets' => $budgets,
            'treasury' => $treasury,
            'rollout' => $rollout,
        ];
    }
    private function buildUnifiedActivityFeed(): LengthAwarePaginator
    {
        $queries = $this->activitySectionQueries();
        if ($queries === []) {
            return $this->emptyPaginator();
        }

        /** @var QueryBuilder $union */
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $paginator = DB::query()
            ->fromSub($union, 'activity_rows')
            ->orderByDesc('occurred_at')
            ->orderByDesc('sort_id')
            ->paginate($this->perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($row): array => $this->normalizeActivityRow($row))
        );

        return $paginator;
    }

    /**
     * @return array<int, QueryBuilder>
     */
    private function activitySectionQueries(): array
    {
        $queries = [];

        if ($this->isModuleVisible('requests')) {
            $queries[] = $this->requestActivityQuery();
        }

        if ($this->isModuleVisible('expenses')) {
            $queries[] = $this->expenseActivityQuery();
        }

        if ($this->canViewVendors() && $this->isModuleVisible('vendors')) {
            $queries[] = $this->vendorActivityQuery();
        }

        if ($this->isModuleVisible('assets')) {
            $queries[] = $this->assetActivityQuery();
        }

        if ($this->isModuleVisible('budgets')) {
            $queries[] = $this->budgetActivityQuery();
        }

        if ($this->isModuleVisible('treasury')) {
            $queries[] = $this->treasuryActivityQuery();
        }

        if ($this->isModuleVisible('rollout')) {
            $queries[] = $this->rolloutActivityQuery();
        }

        return $queries;
    }

    private function requestActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;
        $user = Auth::user();

        $query = DB::table('requests')
            ->leftJoin('users as requester', 'requester.id', '=', 'requests.requested_by')
            ->leftJoin('departments as dept', 'dept.id', '=', 'requests.department_id')
            ->where('requests.company_id', $companyId)
            ->whereNull('requests.deleted_at');

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('requests.request_code', 'like', '%'.$search.'%')
                    ->orWhere('requests.title', 'like', '%'.$search.'%')
                    ->orWhere('requester.name', 'like', '%'.$search.'%');
            });
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('requests.department_id', (int) $this->departmentFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('requests.created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('requests.created_at', '<=', $this->dateTo);
        }

        if ($user && ! in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            if ((string) $user->role === UserRole::Manager->value) {
                $query->where(function (QueryBuilder $inner) use ($user): void {
                    if ($user->department_id) {
                        $inner->where('requests.department_id', (int) $user->department_id)
                            ->orWhere('requests.requested_by', (int) $user->id);
                    } else {
                        $inner->where('requests.requested_by', (int) $user->id);
                    }
                });
            } else {
                $query->where('requests.requested_by', (int) $user->id);
            }
        }

        return $query->select([
            DB::raw("'Requests' as module"),
            DB::raw("'requests' as module_key"),
            'requests.id as record_id',
            DB::raw('NULL as related_id'),
            'requests.id as sort_id',
            'requests.request_code as code',
            'requests.title as title',
            'requests.status as status',
            DB::raw('COALESCE(requests.amount, 0) as amount'),
            DB::raw("COALESCE(dept.name, '-') as department"),
            DB::raw("COALESCE(requester.name, '-') as owner"),
            DB::raw('COALESCE(requests.updated_at, requests.created_at) as occurred_at'),
            DB::raw("'' as source_label"),
            DB::raw("'' as source_code"),
        ]);
    }

    private function expenseActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;
        $user = Auth::user();

        $query = DB::table('expenses')
            ->leftJoin('users as creator', 'creator.id', '=', 'expenses.created_by')
            ->leftJoin('departments as dept', 'dept.id', '=', 'expenses.department_id')
            ->leftJoin('requests as linked_request', 'linked_request.id', '=', 'expenses.request_id')
            ->where('expenses.company_id', $companyId)
            ->whereNull('expenses.deleted_at');

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('expenses.expense_code', 'like', '%'.$search.'%')
                    ->orWhere('expenses.title', 'like', '%'.$search.'%')
                    ->orWhere('linked_request.request_code', 'like', '%'.$search.'%');
            });
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('expenses.department_id', (int) $this->departmentFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('expenses.expense_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('expenses.expense_date', '<=', $this->dateTo);
        }

        if ($user && ! in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            $query->where(function (QueryBuilder $inner) use ($user): void {
                if ($user->department_id) {
                    $inner->where('expenses.department_id', (int) $user->department_id)
                        ->orWhere('expenses.created_by', (int) $user->id);
                } else {
                    $inner->where('expenses.created_by', (int) $user->id);
                }
            });
        }

        return $query->select([
            DB::raw("'Expenses' as module"),
            DB::raw("'expenses' as module_key"),
            'expenses.id as record_id',
            DB::raw('NULL as related_id'),
            'expenses.id as sort_id',
            'expenses.expense_code as code',
            'expenses.title as title',
            'expenses.status as status',
            DB::raw('COALESCE(expenses.amount, 0) as amount'),
            DB::raw("COALESCE(dept.name, '-') as department"),
            DB::raw("COALESCE(creator.name, '-') as owner"),
            DB::raw('COALESCE(expenses.updated_at, expenses.created_at) as occurred_at'),
            DB::raw("CASE WHEN expenses.is_direct = 1 OR expenses.request_id IS NULL THEN 'Direct' ELSE 'From Request' END as source_label"),
            DB::raw("COALESCE(linked_request.request_code, '') as source_code"),
        ]);
    }

    private function vendorActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;

        $query = DB::table('vendor_invoices')
            ->leftJoin('vendors', 'vendors.id', '=', 'vendor_invoices.vendor_id')
            ->where('vendor_invoices.company_id', $companyId)
            ->where('vendor_invoices.status', '!=', VendorInvoice::STATUS_VOID);

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('vendor_invoices.invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('vendors.name', 'like', '%'.$search.'%');
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('vendor_invoices.invoice_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('vendor_invoices.invoice_date', '<=', $this->dateTo);
        }

        return $query->select([
            DB::raw("'Vendors' as module"),
            DB::raw("'vendors' as module_key"),
            'vendor_invoices.id as record_id',
            'vendor_invoices.vendor_id as related_id',
            'vendor_invoices.id as sort_id',
            'vendor_invoices.invoice_number as code',
            DB::raw("COALESCE(vendors.name, 'Vendor invoice') as title"),
            'vendor_invoices.status as status',
            DB::raw('COALESCE(vendor_invoices.outstanding_amount, 0) as amount'),
            DB::raw("'-' as department"),
            DB::raw("'-' as owner"),
            DB::raw('COALESCE(vendor_invoices.updated_at, vendor_invoices.created_at) as occurred_at'),
            DB::raw("'' as source_label"),
            DB::raw("'' as source_code"),
        ]);
    }

    private function assetActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;
        $user = Auth::user();

        $query = DB::table('assets')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'assets.assigned_to_user_id')
            ->leftJoin('departments as dept', 'dept.id', '=', 'assets.assigned_department_id')
            ->where('assets.company_id', $companyId)
            ->whereNull('assets.deleted_at');

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('assets.asset_code', 'like', '%'.$search.'%')
                    ->orWhere('assets.name', 'like', '%'.$search.'%')
                    ->orWhere('assets.serial_number', 'like', '%'.$search.'%');
            });
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('assets.assigned_department_id', (int) $this->departmentFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('assets.acquisition_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('assets.acquisition_date', '<=', $this->dateTo);
        }

        if ($user && ! in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            $query->where(function (QueryBuilder $inner) use ($user): void {
                if ($user->department_id) {
                    $inner->where('assets.assigned_department_id', (int) $user->department_id)
                        ->orWhere('assets.assigned_to_user_id', (int) $user->id)
                        ->orWhere('assets.created_by', (int) $user->id);
                } else {
                    $inner->where('assets.assigned_to_user_id', (int) $user->id)
                        ->orWhere('assets.created_by', (int) $user->id);
                }
            });
        }

        return $query->select([
            DB::raw("'Assets' as module"),
            DB::raw("'assets' as module_key"),
            'assets.id as record_id',
            DB::raw('NULL as related_id'),
            'assets.id as sort_id',
            'assets.asset_code as code',
            'assets.name as title',
            'assets.status as status',
            DB::raw('COALESCE(assets.purchase_amount, 0) as amount'),
            DB::raw("COALESCE(dept.name, '-') as department"),
            DB::raw("COALESCE(assignee.name, '-') as owner"),
            DB::raw('COALESCE(assets.updated_at, assets.created_at) as occurred_at'),
            DB::raw("'' as source_label"),
            DB::raw("'' as source_code"),
        ]);
    }

    private function budgetActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;
        $user = Auth::user();

        $query = DB::table('department_budgets')
            ->leftJoin('departments as dept', 'dept.id', '=', 'department_budgets.department_id')
            ->where('department_budgets.company_id', $companyId)
            ->whereNull('department_budgets.deleted_at');

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('department_budgets.period_type', 'like', '%'.$search.'%')
                    ->orWhere('dept.name', 'like', '%'.$search.'%');
            });
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('department_budgets.department_id', (int) $this->departmentFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('department_budgets.period_start', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('department_budgets.period_end', '<=', $this->dateTo);
        }

        if ($user && ! in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            if ($user->department_id) {
                $query->where('department_budgets.department_id', (int) $user->department_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->select([
            DB::raw("'Budgets' as module"),
            DB::raw("'budgets' as module_key"),
            'department_budgets.id as record_id',
            DB::raw('NULL as related_id'),
            'department_budgets.id as sort_id',
            DB::raw('UPPER(COALESCE(department_budgets.period_type, \'budget\')) as code'),
            DB::raw("'Budget period' as title"),
            'department_budgets.status as status',
            DB::raw('COALESCE(department_budgets.remaining_amount, 0) as amount'),
            DB::raw("COALESCE(dept.name, '-') as department"),
            DB::raw("'-' as owner"),
            DB::raw('COALESCE(department_budgets.updated_at, department_budgets.created_at) as occurred_at'),
            DB::raw("'' as source_label"),
            DB::raw("'' as source_code"),
        ]);
    }

    private function treasuryActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;

        $query = DB::table('reconciliation_exceptions')
            ->where('reconciliation_exceptions.company_id', $companyId);

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('reconciliation_exceptions.exception_code', 'like', '%'.$search.'%')
                    ->orWhere('reconciliation_exceptions.details', 'like', '%'.$search.'%');
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('reconciliation_exceptions.updated_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('reconciliation_exceptions.updated_at', '<=', $this->dateTo);
        }

        return $query->select([
            DB::raw("'Bank Reconciliation' as module"),
            DB::raw("'treasury' as module_key"),
            'reconciliation_exceptions.id as record_id',
            DB::raw('NULL as related_id'),
            'reconciliation_exceptions.id as sort_id',
            DB::raw('UPPER(COALESCE(reconciliation_exceptions.exception_code, \'bank_item\')) as code'),
            DB::raw("'Unresolved bank item' as title"),
            'reconciliation_exceptions.exception_status as status',
            DB::raw('0 as amount'),
            DB::raw("'-' as department"),
            DB::raw("'-' as owner"),
            DB::raw('COALESCE(reconciliation_exceptions.updated_at, reconciliation_exceptions.created_at) as occurred_at'),
            DB::raw("'' as source_label"),
            DB::raw("'' as source_code"),
        ]);
    }

    private function rolloutActivityQuery(): QueryBuilder
    {
        $companyId = (int) Auth::user()?->company_id;

        $query = DB::table('tenant_pilot_wave_outcomes')
            ->leftJoin('users as decided_by', 'decided_by.id', '=', 'tenant_pilot_wave_outcomes.decided_by_user_id')
            ->where('tenant_pilot_wave_outcomes.company_id', $companyId);

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (QueryBuilder $inner) use ($search): void {
                $inner
                    ->where('tenant_pilot_wave_outcomes.wave_label', 'like', '%'.$search.'%')
                    ->orWhere('tenant_pilot_wave_outcomes.notes', 'like', '%'.$search.'%');
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('tenant_pilot_wave_outcomes.decision_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('tenant_pilot_wave_outcomes.decision_at', '<=', $this->dateTo);
        }

        return $query->select([
            DB::raw("'Provider Rollout' as module"),
            DB::raw("'rollout' as module_key"),
            'tenant_pilot_wave_outcomes.id as record_id',
            DB::raw('NULL as related_id'),
            'tenant_pilot_wave_outcomes.id as sort_id',
            DB::raw('UPPER(COALESCE(tenant_pilot_wave_outcomes.wave_label, \'wave\')) as code'),
            DB::raw("'Provider rollout decision' as title"),
            'tenant_pilot_wave_outcomes.outcome as status',
            DB::raw('0 as amount'),
            DB::raw("'-' as department"),
            DB::raw("COALESCE(decided_by.name, '-') as owner"),
            DB::raw('COALESCE(tenant_pilot_wave_outcomes.decision_at, tenant_pilot_wave_outcomes.created_at) as occurred_at'),
            DB::raw("'' as source_label"),
            DB::raw("'' as source_code"),
        ]);
    }

    /**
     * @param  object|array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function normalizeActivityRow(object|array $row): array
    {
        $data = is_array($row) ? $row : (array) $row;
        $moduleKey = (string) ($data['module_key'] ?? '');
        $recordId = (int) ($data['record_id'] ?? 0);
        $relatedId = isset($data['related_id']) ? (int) $data['related_id'] : null;
        $code = (string) ($data['code'] ?? '-');

        $occurredAt = null;
        $occurredAtRaw = $data['occurred_at'] ?? null;
        if ($occurredAtRaw) {
            try {
                $occurredAt = Carbon::parse((string) $occurredAtRaw);
            } catch (\Throwable) {
                $occurredAt = null;
            }
        }

        return [
            'module' => (string) ($data['module'] ?? 'Activity'),
            'code' => $code,
            'title' => (string) ($data['title'] ?? '-'),
            'status' => (string) ($data['status'] ?? '-'),
            'amount' => (int) ($data['amount'] ?? 0),
            'department' => (string) ($data['department'] ?? '-'),
            'owner' => (string) ($data['owner'] ?? '-'),
            'occurred_at' => $occurredAt,
            'source_label' => (string) ($data['source_label'] ?? ''),
            'source_code' => (string) ($data['source_code'] ?? ''),
            'url' => $this->activityRowUrl($moduleKey, $recordId, $code, $relatedId),
        ];
    }

    private function activityRowUrl(string $moduleKey, int $recordId, string $code, ?int $relatedId = null): string
    {
        try {
            return match ($moduleKey) {
                'requests' => route('requests.index', ['open_request_id' => $recordId]),
                'expenses' => route('expenses.index', ['search' => $code]),
                'vendors' => $relatedId ? route('vendors.show', ['vendor' => $relatedId]) : route('vendors.index'),
                'assets' => route('assets.index', ['search' => $code]),
                'budgets' => route('budgets.index'),
                'treasury' => route('treasury.reconciliation-exceptions'),
                default => route('reports.index'),
            };
        } catch (\Throwable) {
            return route('reports.index');
        }
    }

    private function requestQuery(): Builder
    {
        $companyId = (int) Auth::user()?->company_id;

        $query = SpendRequest::query()
            ->where('company_id', $companyId)
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
        $companyId = (int) Auth::user()?->company_id;

        $query = Expense::query()
            ->where('company_id', $companyId)
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
        $companyId = (int) Auth::user()?->company_id;

        $query = VendorInvoice::query()
            ->where('company_id', $companyId)
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
        $companyId = (int) Auth::user()?->company_id;

        $query = Asset::query()
            ->where('company_id', $companyId)
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
        $companyId = (int) Auth::user()?->company_id;

        $query = DepartmentBudget::query()
            ->where('company_id', $companyId)
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

    private function pilotWaveOutcomeQuery(): Builder
    {
        return TenantPilotWaveOutcome::query()
            ->where('company_id', (int) Auth::user()?->company_id)
            ->when($this->search !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('wave_label', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->when($this->dateFrom !== '', fn (Builder $builder) => $builder->whereDate('decision_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $builder) => $builder->whereDate('decision_at', '<=', $this->dateTo));
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
            ['label' => 'Budget to Payment Trace', 'route' => route('reports.financial-trace')],
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
     *   procurement: array{linked_invoices:int, open_exceptions:int, match_pass_rate_percent:float, stale_commitments:int},
     *   budgets: array{active_count:int, allocated:int, used:int, remaining:int},
     *   treasury: array{reconciled_lines:int, open_exceptions:int, unreconciled_value:int},
     *   rollout: array{go:int, hold:int, no_go:int, total:int}
     * }
     */
    private function emptyMetrics(): array
    {
        return [
            'requests' => ['total' => 0, 'in_review' => 0, 'approved' => 0, 'amount' => 0],
            'expenses' => ['total' => 0, 'posted' => 0, 'void' => 0, 'amount' => 0],
            'vendors' => ['outstanding_count' => 0, 'outstanding_amount' => 0, 'overdue_count' => 0],
            'assets' => ['total' => 0, 'assigned' => 0, 'in_maintenance' => 0, 'disposed' => 0],
            'procurement' => ['linked_invoices' => 0, 'open_exceptions' => 0, 'match_pass_rate_percent' => 0.0, 'stale_commitments' => 0],
            'budgets' => ['active_count' => 0, 'allocated' => 0, 'used' => 0, 'remaining' => 0],
            'treasury' => ['reconciled_lines' => 0, 'open_exceptions' => 0, 'unreconciled_value' => 0],
            'rollout' => ['go' => 0, 'hold' => 0, 'no_go' => 0, 'total' => 0],
        ];
    }

    /**
     * @return array{
     *   requests: array{total:int, in_review:int, approved:int, amount:int},
     *   expenses: array{total:int, posted:int, void:int, amount:int},
     *   vendors: array{outstanding_count:int, outstanding_amount:int, overdue_count:int},
     *   assets: array{total:int, assigned:int, in_maintenance:int, disposed:int},
     *   procurement: array{linked_invoices:int, open_exceptions:int, match_pass_rate_percent:float, stale_commitments:int},
     *   budgets: array{active_count:int, allocated:int, used:int, remaining:int},
     *   treasury: array{reconciled_lines:int, open_exceptions:int, unreconciled_value:int},
     *   rollout: array{go:int, hold:int, no_go:int, total:int}
     * }
     */
    private function cachedMetrics(): array
    {
        if (! $this->canUsePerformanceCache()) {
            return $this->buildMetrics();
        }

        $cacheTtl = max(5, (int) config('performance.cache.reports_metrics_ttl_seconds', 60));

        return Cache::remember($this->metricsCacheKey(), now()->addSeconds($cacheTtl), function (): array {
            return $this->buildMetrics();
        });
    }

    private function metricsCacheKey(): string
    {
        $user = Auth::user();
        $fingerprint = md5(json_encode([
            'company_id' => (int) ($user?->company_id ?? 0),
            'user_id' => (int) ($user?->id ?? 0),
            'role' => (string) ($user?->role ?? ''),
            'module_filter' => $this->moduleFilter,
            'search' => $this->search,
            'department_filter' => $this->departmentFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'can_view_vendors' => $this->canViewVendors(),
        ]) ?: '');

        return 'flowdesk:reports:metrics:'.$fingerprint;
    }

    private function canUsePerformanceCache(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return (bool) config('performance.cache.enabled', true);
    }
}






